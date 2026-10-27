<?php
// File: uc3_huy_don_hang_kh.php
require_once 'includes/header.php';

$idKH = $_SESSION['current_customer'];
$msgSuccess = '';
$msgError = '';

if (isset($_POST['reset_sample_order'])) {
    $stmtReset = $pdo->prepare("UPDATE DonHang SET trangThai = 'ChoXuLy', lyDoHuy = NULL WHERE maDonHang = 'DH_CHUXULY_01'");
    $stmtReset->execute();
    $msgSuccess = "Đã khôi phục đơn hàng mẫu DH_CHUXULY_01 về trạng thái 'Chờ xử lý' để bạn test lại!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_confirm_cancel'])) {
    $maDonHang = $_POST['maDonHang'];
    $lyDoChon  = trim(isset($_POST['lyDoChon']) ? $_POST['lyDoChon'] : '');
    $lyDoKhac  = trim(isset($_POST['lyDoKhac']) ? $_POST['lyDoKhac'] : '');

    $lyDoHuy = ($lyDoChon === 'Lý do khác') ? $lyDoKhac : $lyDoChon;

    if ($lyDoHuy === '') {
        $msgError = "Vui lòng chọn lý do hủy (và nhập nội dung chi tiết nếu chọn 'Lý do khác').";
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT * FROM DonHang WHERE maDonHang = ? AND idKhachHang = ? FOR UPDATE");
            $pdo->beginTransaction();
            $stmtCheck->execute(array($maDonHang, $idKH));
            $donHang = $stmtCheck->fetch();

            if (!$donHang || $donHang['trangThai'] !== 'ChoXuLy') {
                $pdo->rollBack();
                $msgError = "Đơn hàng không còn ở trạng thái ‘Chờ xử lý’ nên không thể hủy.";
            } else {
                if (isset($_POST['simulate_error_e1'])) {
                    throw new Exception("Simulated System Error E.1");
                }

                $stmtUpd = $pdo->prepare("UPDATE DonHang SET trangThai = 'DaHuy', lyDoHuy = ? WHERE maDonHang = ?");
                $stmtUpd->execute(array($lyDoHuy, $maDonHang));

                $stmtItems = $pdo->prepare("SELECT * FROM ChiTietDonHang WHERE maDonHang = ?");
                $stmtItems->execute(array($maDonHang));
                $items = $stmtItems->fetchAll();

                $stmtRestore = $pdo->prepare("
                    UPDATE SanPham 
                    SET soLuongTonKho = soLuongTonKho + ?, 
                        soLuongDaBan = GREATEST(0, soLuongDaBan - ?) 
                    WHERE maSanPham = ?
                ");
                foreach ($items as $it) {
                    $stmtRestore->execute(array($it['soLuong'], $it['soLuong'], $it['maSanPham']));
                }

                $maGDHoan = 'GD_REFUND_' . rand(10000, 99999);
                $pt = !empty($donHang['phuongThucThanhToan']) ? $donHang['phuongThucThanhToan'] : 'VNPay';
                $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'HoanTien', 'DaHoanTien', ?)");
                $stmtGD->execute(array($maGDHoan, $maDonHang, $donHang['tongTien'], $pt));

                addNotification($pdo, $donHang['idDoanhNghiep'], "Đơn hàng $maDonHang bị hủy bởi khách", "Lý do hủy: $lyDoHuy. Tồn kho đã được hoàn trả.");
                addNotification($pdo, $idKH, "Xác nhận hủy đơn hàng $maDonHang", "Đã hoàn tiền " . formatVND($donHang['tongTien']) . " qua $pt.");

                $pdo->commit();
                $msgSuccess = "Hủy đơn hàng thành công! Đã cộng lại tồn kho, giảm số lượng đã bán và hoàn tiền <strong>" . formatVND($donHang['tongTien']) . "</strong> về cho khách hàng qua Cổng thanh toán.";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msgError = "Có lỗi xảy ra trong quá trình hủy đơn. Vui lòng thử lại sau.";
        }
    }
}

$stmtOrders = $pdo->prepare("SELECT * FROM DonHang WHERE idKhachHang = ? AND trangThai != 'DonHangTam' ORDER BY ngayDat DESC");
$stmtOrders->execute(array($idKH));
$dsDonHang = $stmtOrders->fetchAll();

$defaultMaDH = isset($dsDonHang[0]['maDonHang']) ? $dsDonHang[0]['maDonHang'] : '';
$maChon = isset($_GET['maDonHang']) ? $_GET['maDonHang'] : $defaultMaDH;
$donChon = null;
foreach ($dsDonHang as $d) {
    if ($d['maDonHang'] === $maChon) {
        $donChon = $d;
        break;
    }
}

$chiTietDonChon = array();
if ($donChon) {
    $stmtCT = $pdo->prepare("SELECT ct.*, sp.tenSanPham, sp.soLuongTonKho, sp.soLuongDaBan FROM ChiTietDonHang ct JOIN SanPham sp ON ct.maSanPham = sp.maSanPham WHERE ct.maDonHang = ?");
    $stmtCT->execute(array($donChon['maDonHang']));
    $chiTietDonChon = $stmtCT->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-danger">Đặc tả Use Case #3 - Actor: Khách hàng & Cổng thanh toán</span>
        <h3 class="fw-bold mb-0">Hủy Đơn Hàng (Dành Cho Khách Hàng)</h3>
    </div>
    <form method="POST">
        <button type="submit" name="reset_sample_order" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise"></i> Khôi phục đơn mẫu DH_CHUXULY_01 để test lại
        </button>
    </form>
</div>

<?php if ($msgSuccess): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> <?php echo $msgSuccess; ?></div>
<?php endif; ?>
<?php if ($msgError): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo $msgError; ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-bold">1. Chọn đơn hàng muốn hủy</div>
            <div class="list-group list-group-flush">
                <?php foreach ($dsDonHang as $dh): 
                    $badgeClass = 'bg-secondary';
                    if ($dh['trangThai'] === 'ChoXuLy') $badgeClass = 'bg-warning text-dark';
                    elseif ($dh['trangThai'] === 'DangGiao') $badgeClass = 'bg-info text-dark';
                    elseif ($dh['trangThai'] === 'DaGiao') $badgeClass = 'bg-success';
                    elseif ($dh['trangThai'] === 'DaHuy') $badgeClass = 'bg-danger';
                ?>
                    <a href="?maDonHang=<?php echo urlencode($dh['maDonHang']); ?>" 
                       class="list-group-item list-group-item-action <?php echo ($donChon && $donChon['maDonHang']===$dh['maDonHang']) ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>Mã đơn: <?php echo htmlspecialchars($dh['maDonHang']); ?></strong>
                            <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($dh['trangThai']); ?></span>
                        </div>
                        <div class="small mt-1 opacity-75">
                            Ngày đặt: <?php echo $dh['ngayDat']; ?> | Tổng tiền: <?php echo formatVND($dh['tongTien']); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($donChon): ?>
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Chi tiết đơn hàng: <?php echo htmlspecialchars($donChon['maDonHang']); ?></span>
                    <span class="badge bg-dark">Phương thức TT: <?php echo htmlspecialchars(!empty($donChon['phuongThucThanhToan']) ? $donChon['phuongThucThanhToan'] : 'VNPay'); ?></span>
                </div>
                <div class="card-body">
                    <?php if ($donChon['trangThai'] !== 'ChoXuLy'): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-info-circle-fill"></i> <strong>Thông báo hệ thống (Luồng thay thế 2.2):</strong><br>
                            “Đơn hàng không còn ở trạng thái ‘Chờ xử lý’ nên không thể hủy.” (Trạng thái hiện tại: <strong><?php echo htmlspecialchars($donChon['trangThai']); ?></strong>)
                            <?php if ($donChon['lyDoHuy']): ?>
                                <div class="mt-1 small text-danger">Lý do đã hủy: <?php echo htmlspecialchars($donChon['lyDoHuy']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <table class="table table-bordered align-middle small">
                        <thead class="table-light">
                            <tr>
                                <th>Sản phẩm</th>
                                <th class="text-center">SL mua</th>
                                <th class="text-end">Đơn giá đã khóa</th>
                                <th class="text-end">Thành tiền</th>
                                <th class="text-center">Tồn kho / Đã bán hiện tại</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chiTietDonChon as $ct): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ct['tenSanPham']); ?></td>
                                    <td class="text-center fw-bold"><?php echo $ct['soLuong']; ?></td>
                                    <td class="text-end"><?php echo formatVND($ct['donGia']); ?></td>
                                    <td class="text-end fw-bold"><?php echo formatVND($ct['thanhTien']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border">Tồn: <?php echo $ct['soLuongTonKho']; ?> | Đã bán: <?php echo $ct['soLuongDaBan']; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="d-flex justify-content-between align-items-center bg-light p-3 rounded mb-3">
                        <span>Số tiền sẽ được hoàn lại (theo giá đã khóa lúc đặt hàng):</span>
                        <strong class="text-danger fs-5"><?php echo formatVND($donChon['tongTien']); ?></strong>
                    </div>

                    <?php if ($donChon['trangThai'] === 'ChoXuLy'): ?>
                        <button type="button" class="btn btn-danger w-100" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                            <i class="bi bi-x-circle"></i> Bước 4: Hủy đơn hàng
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($donChon && $donChon['trangThai'] === 'ChoXuLy'): ?>
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-octagon"></i> Xác nhận hủy đơn hàng <?php echo htmlspecialchars($donChon['maDonHang']); ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="maDonHang" value="<?php echo htmlspecialchars($donChon['maDonHang']); ?>">
                <p class="fw-semibold mb-2">Bước 5 & 6: Vui lòng chọn lý do hủy đơn hàng:</p>
                
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="lyDoChon" id="ld1" value="Đặt nhầm sản phẩm" checked onchange="toggleOtherReason()">
                    <label class="form-check-label" for="ld1">Đặt nhầm sản phẩm</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="lyDoChon" id="ld2" value="Muốn thay đổi địa chỉ nhận hàng" onchange="toggleOtherReason()">
                    <label class="form-check-label" for="ld2">Muốn thay đổi địa chỉ nhận hàng</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="lyDoChon" id="ld3" value="Tìm được giá tốt hơn" onchange="toggleOtherReason()">
                    <label class="form-check-label" for="ld3">Tìm được giá tốt hơn</label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="lyDoChon" id="ld4" value="Lý do khác" onchange="toggleOtherReason()">
                    <label class="form-check-label" for="ld4">Lý do khác (Nhập chi tiết bên dưới)</label>
                </div>

                <div id="otherReasonBox" class="mt-2" style="display:none;">
                    <textarea name="lyDoKhac" class="form-control" rows="2" placeholder="Nhập nội dung lý do hủy đơn..."></textarea>
                </div>

                <hr>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="simulate_error_e1" id="simE1">
                    <label class="form-check-label small text-muted" for="simE1">
                        [Test Luồng ngoại lệ E.1] Giả lập lỗi hệ thống ở bước hoàn trả tồn kho/hoàn tiền để kiểm tra Rollback
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Không hủy (Giữ nguyên đơn)</button>
                <button type="submit" name="btn_confirm_cancel" class="btn btn-danger">Xác nhận hủy</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleOtherReason() {
    var isOther = document.getElementById('ld4').checked;
    document.getElementById('otherReasonBox').style.display = isOther ? 'block' : 'none';
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>