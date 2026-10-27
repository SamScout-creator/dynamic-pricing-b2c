<?php
// File: uc1_thiet_lap_gia.php
require_once 'includes/header.php';

$idDN = $_SESSION['current_seller'];

$stmtList = $pdo->prepare("SELECT * FROM SanPham WHERE idDoanhNghiep = ? AND trangThaiHienThi != 'DaXoa'");
$stmtList->execute(array($idDN));
$dsSanPham = $stmtList->fetchAll();

$defaultMaSP = isset($dsSanPham[0]['maSanPham']) ? $dsSanPham[0]['maSanPham'] : '';
$maSanPhamChon = isset($_GET['maSanPham']) ? $_GET['maSanPham'] : $defaultMaSP;
$spHienTai = null;
foreach ($dsSanPham as $item) {
    if ($item['maSanPham'] === $maSanPhamChon) {
        $spHienTai = $item;
        break;
    }
}

$msgSuccess = '';
$msgWarning = '';
$msgError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $spHienTai) {
    if (isset($_POST['btn_cancel'])) {
        $msgWarning = "Đã hủy bỏ các thay đổi vừa nhập trên biểu mẫu. Khung giá cũ được giữ nguyên.";
    } elseif (isset($_POST['btn_save'])) {
        if ($currentSellerInfo['trangThaiGianHang'] !== 'DangHoatDong') {
            $msgError = "Gian hàng không hoạt động (Trạng thái hiện tại: " . $currentSellerInfo['trangThaiGianHang'] . "). Không thể thiết lập giá sản phẩm!";
        } else {
            $rawGiaCoSo = trim(isset($_POST['giaCoSo']) ? $_POST['giaCoSo'] : '');
            $rawGiaSan  = trim(isset($_POST['giaSan']) ? $_POST['giaSan'] : '');
            $rawGiaTran = trim(isset($_POST['giaTran']) ? $_POST['giaTran'] : '');
            $batDP      = isset($_POST['trangThaiDynamicPricing']) ? 1 : 0;

            $errors = array();
            if ($rawGiaCoSo === '' || $rawGiaSan === '' || $rawGiaTran === '') {
                $errors[] = "Không được để trống bất kỳ trường giá nào (Giá cơ sở, Giá sàn, Giá trần).";
            } elseif (!is_numeric($rawGiaCoSo) || !is_numeric($rawGiaSan) || !is_numeric($rawGiaTran)) {
                $errors[] = "Dữ liệu nhập vào phải là số hợp lệ.";
            } else {
                $giaCoSo = (float)$rawGiaCoSo;
                $giaSan  = (float)$rawGiaSan;
                $giaTran = (float)$rawGiaTran;

                if ($giaSan <= 0) {
                    $errors[] = "Giá sàn phải lớn hơn 0 (Giá sàn > 0).";
                }
                if ($giaSan > $giaCoSo) {
                    $errors[] = "Vi phạm ràng buộc: Giá sàn không được lớn hơn Giá cơ sở (Giá sàn ≤ Giá cơ sở).";
                }
                if ($giaCoSo > $giaTran) {
                    $errors[] = "Vi phạm ràng buộc: Giá cơ sở không được lớn hơn Giá trần (Giá cơ sở ≤ Giá trần).";
                }
            }

            if (!empty($errors)) {
                $msgError = implode("<br>", $errors);
            } else {
                if ($batDP == 1 && $currentSellerInfo['coDynamicPricing'] == 0) {
                    $batDP = 0;
                    $msgWarning = "Gói Cơ bản không hỗ trợ Dynamic Pricing, giá bán sẽ cố định theo Giá cơ sở.";
                }

                try {
                    if (isset($_POST['simulate_db_error'])) {
                        throw new PDOException("Simulated DB Connection Error");
                    }
                    $pdo->beginTransaction();

                    $giaCu = $spHienTai['giaBanHienTai'];
                    $stmtUpdate = $pdo->prepare("
                        UPDATE SanPham 
                        SET giaCoSo = ?, giaSan = ?, giaTran = ?, giaBanHienTai = ?, trangThaiDynamicPricing = ?
                        WHERE maSanPham = ? AND idDoanhNghiep = ?
                    ");
                    $stmtUpdate->execute(array($giaCoSo, $giaSan, $giaTran, $giaCoSo, $batDP, $spHienTai['maSanPham'], $idDN));

                    $stmtLog = $pdo->prepare("INSERT INTO LichSuThayDoiGia (maSanPham, giaTruoc, giaSau, thoiDiemThayDoi, lyDoThayDoi) VALUES (?, ?, ?, NOW(), ?)");
                    $stmtLog->execute(array(
                        $spHienTai['maSanPham'],
                        $giaCu,
                        $giaCoSo,
                        "Doanh nghiệp thiết lập khung giá mới (DP: " . ($batDP ? 'Bật' : 'Tắt') . ")"
                    ));

                    $pdo->commit();
                    $msgSuccess = "Thiết lập giá sản phẩm thành công! Giá bán hiện tại đã được khởi tạo bằng Giá cơ sở (" . formatVND($giaCoSo) . ").";

                    $stmtRefresh = $pdo->prepare("SELECT * FROM SanPham WHERE maSanPham = ?");
                    $stmtRefresh->execute(array($spHienTai['maSanPham']));
                    $spHienTai = $stmtRefresh->fetch();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $msgError = "Không thể lưu thông tin giá sản phẩm. Vui lòng thử lại sau.";
                }
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-primary">Đặc tả Use Case #1 - Actor: Doanh nghiệp</span>
        <h3 class="fw-bold mb-0">Thiết Lập Giá Sản Phẩm (Khung Giá Động)</h3>
    </div>
    <div class="text-end">
        <span class="badge bg-dark">Gói hiện tại: <?php echo htmlspecialchars($currentSellerInfo['tenGoi']); ?></span>
        <span class="badge <?php echo ($currentSellerInfo['trangThaiGianHang']=='DangHoatDong')?'bg-success':'bg-danger'; ?>">
            Trạng thái gian hàng: <?php echo htmlspecialchars($currentSellerInfo['trangThaiGianHang']); ?>
        </span>
    </div>
</div>

<?php if ($currentSellerInfo['trangThaiGianHang'] !== 'DangHoatDong'): ?>
    <div class="alert alert-danger d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-exclamation-octagon-fill me-2"></i>
            <strong>Cảnh báo Tiền điều kiện (Sequence Diagram):</strong> Gian hàng của bạn đang ở trạng thái <code><?php echo htmlspecialchars($currentSellerInfo['trangThaiGianHang']); ?></code> nên bị khóa quyền thiết lập giá sản phẩm.
        </div>
        <a href="uc2_thanh_toan_goi.php" class="btn btn-sm btn-danger">Sang UC2 Thanh toán kích hoạt ngay</a>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-bold">1. Chọn sản phẩm của gian hàng</div>
            <div class="list-group list-group-flush">
                <?php foreach ($dsSanPham as $sp): ?>
                    <a href="?maSanPham=<?php echo urlencode($sp['maSanPham']); ?>" 
                       class="list-group-item list-group-item-action <?php echo ($spHienTai && $spHienTai['maSanPham']===$sp['maSanPham']) ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?php echo htmlspecialchars($sp['tenSanPham']); ?></strong>
                            <span class="badge <?php echo $sp['trangThaiDynamicPricing'] ? 'bg-warning text-dark' : 'bg-secondary'; ?>">
                                <?php echo $sp['trangThaiDynamicPricing'] ? 'DP: Bật' : 'DP: Tắt'; ?>
                            </span>
                        </div>
                        <div class="small mt-1 opacity-75">
                            Mã: <?php echo htmlspecialchars($sp['maSanPham']); ?> | Giá hiện tại: <?php echo formatVND($sp['giaBanHienTai']); ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-light small text-muted">
                <i class="bi bi-lightbulb"></i> <strong>Mẹo test Luồng thay thế 6.1:</strong> Bấm nút <em>"DN02 (Gói Cơ bản)"</em> trên thanh đen đầu trang, sau đó sang UC2 kích hoạt gian hàng rồi bật thử công tắc Dynamic Pricing tại đây!
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <?php if ($msgWarning): ?>
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-info-circle-fill me-1"></i> <?php echo $msgWarning; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($msgError): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-x-circle-fill me-1"></i> <strong>Lỗi dữ liệu nhập:</strong><br><?php echo $msgError; ?>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-dismiss="alert">Đóng</button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($msgSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-1"></i> <?php echo $msgSuccess; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($spHienTai): ?>
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">2. Thông tin & Thiết lập khung giá cho: <span class="text-primary"><?php echo htmlspecialchars($spHienTai['tenSanPham']); ?></span></span>
                <span class="badge bg-primary-subtle text-primary">Giá bán hiện tại: <?php echo formatVND($spHienTai['giaBanHienTai']); ?></span>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Giá sàn (VNĐ) <span class="text-danger">*</span></label>
                            <input type="text" name="giaSan" class="form-control" 
                                   value="<?php echo htmlspecialchars(isset($_POST['giaSan']) ? $_POST['giaSan'] : (float)$spHienTai['giaSan']); ?>">
                            <div class="form-text">Mức giá tối thiểu (> 0)</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Giá cơ sở (VNĐ) <span class="text-danger">*</span></label>
                            <input type="text" name="giaCoSo" class="form-control border-primary" 
                                   value="<?php echo htmlspecialchars(isset($_POST['giaCoSo']) ? $_POST['giaCoSo'] : (float)$spHienTai['giaCoSo']); ?>">
                            <div class="form-text text-primary">Giá gốc khởi tạo ban đầu</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Giá trần (VNĐ) <span class="text-danger">*</span></label>
                            <input type="text" name="giaTran" class="form-control" 
                                   value="<?php echo htmlspecialchars(isset($_POST['giaTran']) ? $_POST['giaTran'] : (float)$spHienTai['giaTran']); ?>">
                            <div class="form-text">Mức giá tối đa cho phép</div>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="dpSwitch" name="trangThaiDynamicPricing" value="1"
                                <?php echo ($spHienTai['trangThaiDynamicPricing'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold" for="dpSwitch">
                                Bật cơ chế điều chỉnh giá tự động (Dynamic Pricing)
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1">
                            Quy tắc nghiệp vụ: Yêu cầu <code>0 &lt; Giá sàn ≤ Giá cơ sở ≤ Giá trần</code>. Chỉ khả dụng cho gói <strong>Nâng cao</strong> hoặc <strong>Doanh nghiệp</strong>.
                        </small>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="simulate_db_error" id="simErr">
                        <label class="form-check-label small text-muted" for="simErr">
                            [Chế độ Test Luồng ngoại lệ 7.1] Giả lập sự cố mất kết nối CSDL khi lưu để kiểm tra cơ chế Rollback
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="btn_save" class="btn btn-primary px-4">
                            <i class="bi bi-floppy"></i> Lưu thay đổi
                        </button>
                        <button type="submit" name="btn_cancel" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i> Hủy
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>