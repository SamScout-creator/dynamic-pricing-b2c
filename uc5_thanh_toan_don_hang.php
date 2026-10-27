<?php
// File: uc5_thanh_toan_don_hang.php
require_once 'includes/header.php';

$idKH = $_SESSION['current_customer'];
$msgAlert = '';
$alertType = 'info';
$viewMode = isset($_GET['mode']) ? $_GET['mode'] : 'checkout';

if (isset($_POST['reset_pricelock_order'])) {
    $pdo->exec("UPDATE DonHang SET trangThai = 'DonHangTam', thoiDiemBatDauKhoaGia = NOW(), thoiDiemHetHanKhoaGia = DATE_ADD(NOW(), INTERVAL 15 MINUTE), soLanThuThanhToan = 0, daTamGiuKho = 1 WHERE maDonHang = 'DH_PRICELOCK_03'");
    $pdo->exec("UPDATE SanPham SET soLuongTamGiu = 2 WHERE maSanPham = 'SP01'");
    $msgAlert = "Đã làm mới phiên khóa giá 15 phút (0/3 lần thử) cho đơn hàng mẫu <strong>DH_PRICELOCK_03</strong>!";
    $alertType = 'success';
}

if (isset($_POST['force_expire_pricelock'])) {
    $maDH = $_POST['maDonHang'];
    $stmtExp = $pdo->prepare("UPDATE DonHang SET thoiDiemHetHanKhoaGia = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE maDonHang = ?");
    $stmtExp->execute(array($maDH));
    $msgAlert = "Đã giả lập tua thời gian quá 15 phút! Bây giờ bạn có thể bấm Thanh toán hoặc Xác nhận OTP để kiểm tra luồng hết hạn Price Lock (4.1 / 6.1 / 12.1).";
    $alertType = 'warning';
}

function releaseReservedStock($pdo, $maDonHang) {
    $stmt = $pdo->prepare("SELECT daTamGiuKho FROM DonHang WHERE maDonHang = ?");
    $stmt->execute(array($maDonHang));
    $dh = $stmt->fetch();
    if ($dh && $dh['daTamGiuKho'] == 1) {
        $items = $pdo->prepare("SELECT maSanPham, soLuong FROM ChiTietDonHang WHERE maDonHang = ?");
        $items->execute(array($maDonHang));
        foreach ($items->fetchAll() as $it) {
            $stmtRel = $pdo->prepare("UPDATE SanPham SET soLuongTamGiu = GREATEST(0, soLuongTamGiu - ?) WHERE maSanPham = ?");
            $stmtRel->execute(array($it['soLuong'], $it['maSanPham']));
        }
        $stmtUpd = $pdo->prepare("UPDATE DonHang SET daTamGiuKho = 0 WHERE maDonHang = ?");
        $stmtUpd->execute(array($maDonHang));
    }
}

$maDHChon = isset($_GET['maDonHang']) ? $_GET['maDonHang'] : 'DH_PRICELOCK_03';
$stmtDH = $pdo->prepare("SELECT * FROM DonHang WHERE maDonHang = ? AND idKhachHang = ?");
$stmtDH->execute(array($maDHChon, $idKH));
$donHang = $stmtDH->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_action']) && $donHang) {
    $act = $_POST['checkout_action'];

    if ($act === 'cancel_step5') {
        $pdo->beginTransaction();
        releaseReservedStock($pdo, $donHang['maDonHang']);
        $stmtCancel = $pdo->prepare("UPDATE DonHang SET trangThai = 'DaHuy', lyDoHuy = 'Khách hàng chủ động hủy phiên thanh toán tại bước 5' WHERE maDonHang = ?");
        $stmtCancel->execute(array($donHang['maDonHang']));
        $pdo->commit();

        $alertType = 'warning';
        $msgAlert = "<strong>[Luồng thay thế 5.1]:</strong> Đã hủy phiên thanh toán và giải phóng số lượng tồn kho tạm giữ. Lần đặt hàng sau sẽ áp dụng giá hiện hành mới nhất.";
    } elseif ($act === 'proceed_gateway') {
        $hoTen  = trim(isset($_POST['hoTenNguoiNhan']) ? $_POST['hoTenNguoiNhan'] : '');
        $soDT   = trim(isset($_POST['soDTNhanHang']) ? $_POST['soDTNhanHang'] : '');
        $diaChi = trim(isset($_POST['diaChiNhanHang']) ? $_POST['diaChiNhanHang'] : '');
        $phuongThuc = isset($_POST['phuongThucThanhToan']) ? $_POST['phuongThucThanhToan'] : 'VNPay';

        $fieldErrors = array();
        if ($hoTen === '') $fieldErrors[] = "Vui lòng nhập Họ tên người nhận.";
        if (!preg_match('/^[0-9]{9,11}$/', $soDT)) $fieldErrors[] = "Số điện thoại nhận hàng không hợp lệ (9-11 chữ số).";
        if (strlen($diaChi) < 5) $fieldErrors[] = "Vui lòng nhập địa chỉ nhận hàng đầy đủ.";

        if (!empty($fieldErrors)) {
            $alertType = 'danger';
            $msgAlert = "<strong>[Luồng thay thế 2.1 - Thiếu/Sai thông tin nhận hàng]:</strong><br>" . implode("<br>", $fieldErrors);
        } else {
            $conHieuLuc = (strtotime($donHang['thoiDiemHetHanKhoaGia']) > time());
            if (!$conHieuLuc) {
                $pdo->beginTransaction();
                releaseReservedStock($pdo, $donHang['maDonHang']);
                $stmtExp = $pdo->prepare("UPDATE DonHang SET trangThai = 'HetHan' WHERE maDonHang = ?");
                $stmtExp->execute(array($donHang['maDonHang']));
                $pdo->commit();

                $alertType = 'danger';
                $msgAlert = "<strong>[Luồng ngoại lệ 4.1 / 6.1]:</strong> “Phiên giữ giá đã hết hạn, vui lòng đặt hàng lại”. Hệ thống đã tự động giải phóng tồn kho tạm giữ.";
            } else {
                $thongTinGop = "$hoTen - $soDT - $diaChi";
                $stmtSaveInfo = $pdo->prepare("UPDATE DonHang SET hoTenNguoiNhan = ?, soDTNhanHang = ?, diaChiNhanHang = ?, thongTinNhanHang = ?, phuongThucThanhToan = ? WHERE maDonHang = ?");
                $stmtSaveInfo->execute(array($hoTen, $soDT, $diaChi, $thongTinGop, $phuongThuc, $donHang['maDonHang']));
                $viewMode = 'gateway';
            }
        }
    }
    $stmtDH->execute(array($maDHChon, $idKH));
    $donHang = $stmtDH->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gateway_pay_action']) && $donHang) {
    $gwAct = $_POST['gateway_pay_action'];
    $maGD = 'GD_DH_' . rand(10000, 99999);
    $conHieuLucLucNhanKetQua = (strtotime($donHang['thoiDiemHetHanKhoaGia']) > time());
    if (isset($_POST['simulate_late_webhook_121'])) {
        $conHieuLucLucNhanKetQua = false;
    }
    $pt = !empty($donHang['phuongThucThanhToan']) ? $donHang['phuongThucThanhToan'] : 'VNPay';

    if ($gwAct === 'pay_fail' || $gwAct === 'cancel_at_gateway') {
        $lanThuMoi = $donHang['soLanThuThanhToan'] + 1;
        $trangThaiGD = ($gwAct === 'cancel_at_gateway') ? 'DaHuy' : 'ThatBai';

        $pdo->beginTransaction();
        $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanDonHang', ?, ?)");
        $stmtGD->execute(array($maGD, $donHang['maDonHang'], $donHang['tongTien'], $trangThaiGD, $pt));
        $stmtTry = $pdo->prepare("UPDATE DonHang SET soLanThuThanhToan = ? WHERE maDonHang = ?");
        $stmtTry->execute(array($lanThuMoi, $donHang['maDonHang']));

        if ($lanThuMoi >= 3 || !$conHieuLucLucNhanKetQua) {
            releaseReservedStock($pdo, $donHang['maDonHang']);
            $stmtCancel = $pdo->prepare("UPDATE DonHang SET trangThai = 'DaHuy', lyDoHuy = 'Quá 3 lần thử thanh toán thất bại hoặc hết hạn Price Lock' WHERE maDonHang = ?");
            $stmtCancel->execute(array($donHang['maDonHang']));
            $pdo->commit();

            $alertType = 'danger';
            $msgAlert = "<strong>[Luồng ngoại lệ 11.1.3 - Đã thử $lanThuMoi/3 lần]:</strong> Bạn đã dùng hết tối đa 3 lần thử thanh toán (hoặc hết hạn Price Lock). Hệ thống đã tự động hủy phiên và giải phóng tồn kho tạm giữ!";
            $viewMode = 'checkout';
        } else {
            $pdo->commit();
            $alertType = 'warning';
            $msgAlert = "<strong>[Luồng ngoại lệ 11.1.4 - Lần thử $lanThuMoi/3 thất bại]:</strong> Giao dịch không thành công. Bạn còn <strong>" . (3 - $lanThuMoi) . " lượt thử</strong> trong thời gian Price Lock còn hiệu lực.";
            $viewMode = 'checkout';
        }
    } elseif ($gwAct === 'pay_success') {
        if (!$conHieuLucLucNhanKetQua) {
            $pdo->beginTransaction();
            releaseReservedStock($pdo, $donHang['maDonHang']);
            $stmtExp = $pdo->prepare("UPDATE DonHang SET trangThai = 'HetHan', lyDoHuy = 'Thanh toán thành công nhưng Price Lock đã hết hạn (Đã hoàn tiền)' WHERE maDonHang = ?");
            $stmtExp->execute(array($donHang['maDonHang']));
            $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'HoanTien', 'DaHoanTien', ?)");
            $stmtGD->execute(array($maGD, $donHang['maDonHang'], $donHang['tongTien'], $pt));
            addNotification($pdo, $idKH, "Hoàn tiền do quá hạn Price Lock 15 phút", "Đơn $maDHChon thanh toán muộn sau khi hết hạn khóa giá. Đã tự động hoàn lại " . formatVND($donHang['tongTien']) . ".");
            $pdo->commit();

            $alertType = 'warning';
            $msgAlert = "<strong>[Luồng ngoại lệ 12.1 - Thanh toán trả về sau khi hết hạn Price Lock]:</strong> Hệ thống không xác nhận đơn hàng, đã giải phóng tồn kho tạm giữ và tự động gửi yêu cầu <strong>Hoàn tiền (" . formatVND($donHang['tongTien']) . ")</strong> về cho bạn!";
            $viewMode = 'checkout';
        } else {
            $pdo->beginTransaction();
            $items = $pdo->prepare("SELECT maSanPham, soLuong FROM ChiTietDonHang WHERE maDonHang = ?");
            $items->execute(array($donHang['maDonHang']));
            foreach ($items->fetchAll() as $it) {
                $stmtTruKho = $pdo->prepare("
                    UPDATE SanPham 
                    SET soLuongTonKho = GREATEST(0, soLuongTonKho - ?),
                        soLuongTamGiu = GREATEST(0, soLuongTamGiu - ?),
                        soLuongDaBan = soLuongDaBan + ?
                    WHERE maSanPham = ?
                ");
                $stmtTruKho->execute(array($it['soLuong'], $it['soLuong'], $it['soLuong'], $it['maSanPham']));
            }

            $stmtOk = $pdo->prepare("UPDATE DonHang SET trangThai = 'ChoXuLy', daTamGiuKho = 0 WHERE maDonHang = ?");
            $stmtOk->execute(array($donHang['maDonHang']));
            $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanDonHang', 'ThanhCong', ?)");
            $stmtGD->execute(array($maGD, $donHang['maDonHang'], $donHang['tongTien'], $pt));

            addNotification($pdo, $donHang['idDoanhNghiep'], "Đơn hàng mới #" . $donHang['maDonHang'], "Khách hàng đã thanh toán thành công " . formatVND($donHang['tongTien']) . " trong thời gian Price Lock.");
            addNotification($pdo, $idKH, "Xác nhận đơn hàng #" . $donHang['maDonHang'], "Thanh toán thành công, mức giá khóa đã được chốt.");
            $pdo->commit();

            $alertType = 'success';
            $msgAlert = "<strong>Bước 15: “Thanh toán thành công, đơn hàng đã được xác nhận.”</strong> Mức giá khóa đã được chốt, tồn kho chính thức đã trừ và đơn hàng đã chuyển sang trạng thái <code>Chờ xử lý</code> (Bạn có thể sang <strong>UC3</strong> để test hủy đơn này ngay!).";
            $viewMode = 'checkout';
        }
    }

    $stmtDH->execute(array($maDHChon, $idKH));
    $donHang = $stmtDH->fetch();
}

$chiTiet = array();
$secondsLeft = 0;
if ($donHang) {
    $stmtCT = $pdo->prepare("SELECT ct.*, sp.tenSanPham, sp.giaBanHienTai FROM ChiTietDonHang ct JOIN SanPham sp ON ct.maSanPham = sp.maSanPham WHERE ct.maDonHang = ?");
    $stmtCT->execute(array($donHang['maDonHang']));
    $chiTiet = $stmtCT->fetchAll();
    $secondsLeft = max(0, strtotime($donHang['thoiDiemHetHanKhoaGia']) - time());
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-success">Đặc tả Use Case #5 - Actor: Khách hàng & Cổng thanh toán</span>
        <h3 class="fw-bold mb-0">Thanh Toán Đơn Hàng (Tích Hợp Khóa Giá Price Lock 15 Phút)</h3>
    </div>
    <div class="d-flex gap-2">
        <?php if ($donHang && $donHang['trangThai'] === 'DonHangTam'): ?>
        <form method="POST">
            <input type="hidden" name="maDonHang" value="<?php echo htmlspecialchars($donHang['maDonHang']); ?>">
            <button type="submit" name="force_expire_pricelock" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-stopwatch"></i> Giả lập hết hạn 15 phút ngay
            </button>
        </form>
        <?php endif; ?>
        <form method="POST">
            <button type="submit" name="reset_pricelock_order" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-repeat"></i> Tạo lại phiên Price Lock 15p mẫu
            </button>
        </form>
    </div>
</div>

<?php if ($msgAlert): ?>
    <div class="alert alert-<?php echo $alertType; ?> shadow-sm"><?php echo $msgAlert; ?></div>
<?php endif; ?>

<?php if ($donHang): ?>
    <div class="card mb-4 border-start border-4 <?php echo ($secondsLeft > 0 && $donHang['trangThai']==='DonHangTam') ? 'border-success' : 'border-danger'; ?>">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1">
                    Mã phiên đơn hàng: <span class="text-primary"><?php echo htmlspecialchars($donHang['maDonHang']); ?></span>
                    <span class="badge bg-dark ms-2">Trạng thái: <?php echo htmlspecialchars($donHang['trangThai']); ?></span>
                </h5>
                <div class="text-muted small">
                    Bắt đầu khóa giá: <?php echo $donHang['thoiDiemBatDauKhoaGia']; ?> | Hết hạn lúc: <strong><?php echo $donHang['thoiDiemHetHanKhoaGia']; ?></strong>
                </div>
            </div>
            <div class="d-flex align-items-center gap-4">
                <div class="text-center">
                    <div class="small text-muted">Số lần thử thanh toán</div>
                    <span class="badge bg-warning text-dark fs-6"><?php echo $donHang['soLanThuThanhToan']; ?> / 3 lần</span>
                </div>
                <div class="text-center">
                    <div class="small text-muted">Thời gian Price Lock còn lại</div>
                    <span id="countdownTimer" class="badge <?php echo ($secondsLeft > 0) ? 'bg-success' : 'bg-danger'; ?> fs-5">
                        <?php echo gmdate("i:s", $secondsLeft); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($viewMode === 'gateway' && $donHang['trangThai'] === 'DonHangTam'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card border-success shadow">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <span class="fw-bold"><i class="bi bi-shield-lock-fill"></i> CỔNG THANH TOÁN ĐƠN HÀNG (<?php echo htmlspecialchars($donHang['phuongThucThanhToan']); ?>)</span>
                        <span>Lần thử thứ: <?php echo $donHang['soLanThuThanhToan'] + 1; ?>/3</span>
                    </div>
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                            <div>
                                <div class="text-muted small">Người nhận hàng:</div>
                                <strong><?php echo htmlspecialchars($donHang['thongTinNhanHang']); ?></strong>
                            </div>
                            <div class="text-end">
                                <div class="text-muted small">Số tiền theo giá đã khóa (Price Lock):</div>
                                <strong class="text-danger fs-4"><?php echo formatVND($donHang['tongTien']); ?></strong>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bước 8: Thông tin thẻ / Ví thanh toán</label>
                                <input type="text" class="form-control" value="9704 2292 8811 0029 - NGUYEN VAN KHACH" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bước 9 & 10: Nhập mã OTP gửi về điện thoại (Mặc định: <code>123456</code>)</label>
                                <input type="text" name="otp" class="form-control text-center fw-bold" value="123456">
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="simulate_late_webhook_121" id="sim121">
                                <label class="form-check-label small text-muted" for="sim121">
                                    [Test Luồng ngoại lệ 12.1] Giả lập Cổng thanh toán trừ tiền thành công nhưng trả kết quả về muộn sau khi Price Lock đã hết hạn 15 phút (Tự động Hoàn tiền & Giải phóng kho)
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" name="gateway_pay_action" value="pay_success" class="btn btn-success btn-lg">
                                    <i class="bi bi-check-circle"></i> Bước 10: Nhập OTP & Xác nhận thanh toán (Thành công)
                                </button>
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6">
                                        <button type="submit" name="gateway_pay_action" value="pay_fail" class="btn btn-outline-danger w-100 btn-sm">
                                            Giả lập Thẻ lỗi / Không đủ số dư (Luồng 11.1)
                                        </button>
                                    </div>
                                    <div class="col-md-6">
                                        <button type="submit" name="gateway_pay_action" value="cancel_at_gateway" class="btn btn-outline-secondary w-100 btn-sm">
                                            Hủy/Thoát tại Cổng thanh toán (Luồng 8.1)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($donHang['trangThai'] === 'DonHangTam'): ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold">Sản phẩm đang được Khóa giá & Tạm giữ tồn kho</div>
                    <div class="card-body">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Sản phẩm</th>
                                    <th class="text-center">SL</th>
                                    <th class="text-end">Giá đã khóa</th>
                                    <th class="text-end">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($chiTiet as $ct): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ct['tenSanPham']); ?></td>
                                        <td class="text-center"><?php echo $ct['soLuong']; ?></td>
                                        <td class="text-end text-success fw-bold"><?php echo formatVND($ct['donGia']); ?></td>
                                        <td class="text-end fw-bold"><?php echo formatVND($ct['thanhTien']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="d-flex justify-content-between border-top pt-3">
                            <span class="fw-bold">Tổng số tiền thanh toán (Đã khóa giá):</span>
                            <strong class="text-danger fs-4"><?php echo formatVND($donHang['tongTien']); ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white fw-bold">Bước 1 -> 5: Thông tin nhận hàng & Phương thức thanh toán</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-2">
                                <label class="form-label fw-semibold">Họ và tên người nhận <span class="text-danger">*</span></label>
                                <input type="text" name="hoTenNguoiNhan" class="form-control" value="<?php echo htmlspecialchars(isset($donHang['hoTenNguoiNhan']) ? $donHang['hoTenNguoiNhan'] : ''); ?>">
                            </div>
                            <div class="mb-2">
                                <label class="form-label fw-semibold">Số điện thoại nhận hàng <span class="text-danger">*</span></label>
                                <input type="text" name="soDTNhanHang" class="form-control" value="<?php echo htmlspecialchars(isset($donHang['soDTNhanHang']) ? $donHang['soDTNhanHang'] : ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Địa chỉ nhận hàng chi tiết <span class="text-danger">*</span></label>
                                <input type="text" name="diaChiNhanHang" class="form-control" value="<?php echo htmlspecialchars(isset($donHang['diaChiNhanHang']) ? $donHang['diaChiNhanHang'] : ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Bước 3: Chọn phương thức thanh toán</label>
                                <select name="phuongThucThanhToan" class="form-select">
                                    <option value="VNPay">Cổng thanh toán VNPay</option>
                                    <option value="MoMo">Ví điện tử MoMo</option>
                                    <option value="Thẻ quốc tế">Thẻ quốc tế Visa/Mastercard</option>
                                </select>
                            </div>
                            <div class="d-flex gap-2">
                                <button type="submit" name="checkout_action" value="proceed_gateway" class="btn btn-success flex-grow-1">
                                    <i class="bi bi-credit-card-2-front"></i> Bước 5: Xác nhận thanh toán
                                </button>
                                <button type="submit" name="checkout_action" value="cancel_step5" class="btn btn-outline-danger">
                                    Hủy phiên (Luồng 5.1)
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
var remaining = <?php echo (int)$secondsLeft; ?>;
var timerEl = document.getElementById('countdownTimer');
if (timerEl && remaining > 0) {
    setInterval(function() {
        if (remaining > 0) {
            remaining--;
            var m = Math.floor(remaining / 60);
            var s = remaining % 60;
            timerEl.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            if (remaining === 0) {
                timerEl.className = 'badge bg-danger fs-5';
                timerEl.textContent = '00:00 (Hết hạn)';
            }
        }
    }, 1000);
}
</script>

<?php require_once 'includes/footer.php'; ?>