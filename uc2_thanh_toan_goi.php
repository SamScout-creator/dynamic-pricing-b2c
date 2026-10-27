<?php
// File: uc2_thanh_toan_goi.php
require_once 'includes/header.php';

$idDN = $_SESSION['current_seller'];
$step = isset($_GET['step']) ? $_GET['step'] : 'select_method';
$msgAlert = '';
$alertType = 'info';

if (isset($_POST['create_new_contract'])) {
    $maHDMoi = 'HD_DV_' . rand(100, 999);
    $maGoi = $_POST['maGoi'];
    $chuKy = $_POST['chuKy'];
    $stmtGoi = $pdo->prepare("SELECT * FROM GoiDichVu WHERE maGoi = ?");
    $stmtGoi->execute(array($maGoi));
    $goiInfo = $stmtGoi->fetch();
    $soTien = ($chuKy === 'Năm') ? $goiInfo['goiDichVuNam'] : $goiInfo['goiDichVuThang'];

    $stmtIns = $pdo->prepare("INSERT INTO HopDongDichVu (maHopDong, idDoanhNghiep, maGoi, ngayBatDau, ngayKetThuc, chuKyThanhToan, tongTien, trangThai) VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), ?, ?, 'ChoThanhToan')");
    $stmtIns->execute(array($maHDMoi, $idDN, $maGoi, $chuKy, $soTien));
}

$stmtHD = $pdo->prepare("
    SELECT hd.*, g.tenGoi, g.gioiHanSanPham, g.coDynamicPricing 
    FROM HopDongDichVu hd 
    JOIN GoiDichVu g ON hd.maGoi = g.maGoi 
    WHERE hd.idDoanhNghiep = ? AND hd.trangThai IN ('ChoThanhToan', 'ThanhToanThatBai')
    ORDER BY hd.maHopDong DESC LIMIT 1
");
$stmtHD->execute(array($idDN));
$hopDong = $stmtHD->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn_init_payment']) && $hopDong) {
    $_SESSION['uc2_phuong_thuc'] = $_POST['phuongThuc'];
    $_SESSION['uc2_otp'] = '123456';
    $step = 'gateway';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['gateway_action']) && $hopDong) {
    $action = $_POST['gateway_action'];
    $phuongThuc = isset($_SESSION['uc2_phuong_thuc']) ? $_SESSION['uc2_phuong_thuc'] : 'VNPay';
    $maGD = 'GD_DV_' . rand(10000, 99999);
    $otpInput = isset($_POST['otp_code']) ? $_POST['otp_code'] : '';

    if ($action === 'cancel') {
        $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanGoiDichVu', 'DaHuy', ?)");
        $stmtGD->execute(array($maGD, $hopDong['maHopDong'], $hopDong['tongTien'], $phuongThuc));
        $stmtUp = $pdo->prepare("UPDATE HopDongDichVu SET trangThai = 'ChoThanhToan' WHERE maHopDong = ?");
        $stmtUp->execute(array($hopDong['maHopDong']));

        $alertType = 'warning';
        $msgAlert = "<strong>[Luồng thay thế 7.1] Đã hủy giao dịch:</strong> Cổng thanh toán gửi phản hồi hủy về sàn. Hệ thống giữ nguyên trạng thái cũ và điều hướng về trang thông tin gói dịch vụ.";
        $step = 'select_method';
    } elseif ($action === 'fail_card' || $otpInput !== '123456') {
        $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanGoiDichVu', 'ThatBai', ?)");
        $stmtGD->execute(array($maGD, $hopDong['maHopDong'], $hopDong['tongTien'], $phuongThuc));
        $stmtUp = $pdo->prepare("UPDATE HopDongDichVu SET trangThai = 'ThanhToanThatBai' WHERE maHopDong = ?");
        $stmtUp->execute(array($hopDong['maHopDong']));

        $alertType = 'danger';
        $msgAlert = "<strong>[Luồng ngoại lệ 9.1] Thanh toán thất bại:</strong> Cổng thanh toán báo lỗi (Sai thông tin thẻ / Không đủ số dư / Mã OTP không đúng). Trạng thái gian hàng được giữ nguyên.";
        $step = 'failed_retry';
    } elseif ($action === 'network_error') {
        $pdo->beginTransaction();
        $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanGoiDichVu', 'ThanhCong', ?)");
        $stmtGD->execute(array($maGD, $hopDong['maHopDong'], $hopDong['tongTien'], $phuongThuc));
        $stmtUp = $pdo->prepare("UPDATE HopDongDichVu SET trangThai = 'DaThanhToan' WHERE maHopDong = ?");
        $stmtUp->execute(array($hopDong['maHopDong']));
        $stmtDN = $pdo->prepare("UPDATE DoanhNghiep SET trangThaiGianHang = 'DangHoatDong', maGoiHienTai = ? WHERE id = ?");
        $stmtDN->execute(array($hopDong['maGoi'], $idDN));
        addNotification($pdo, $idDN, "Đối soát tự động gói dịch vụ", "Tín hiệu từ Cổng thanh toán bị gián đoạn nhưng tiến trình đối soát tự động đã xác nhận thanh toán thành công và kích hoạt gian hàng.");
        $pdo->commit();

        $alertType = 'success';
        $msgAlert = "<strong>[Luồng ngoại lệ 9.2 - Đối soát tự động thành công]:</strong> Tín hiệu từ Cổng thanh toán bị gián đoạn sau khi trừ tiền. Hệ thống đã kích hoạt tiến trình đối soát tự động, xác nhận giao dịch thành công và cập nhật trạng thái gian hàng sang <strong>Đang hoạt động</strong>!";
        $step = 'done';
    } elseif ($action === 'confirm_success') {
        $pdo->beginTransaction();
        $stmtGD = $pdo->prepare("INSERT INTO GiaoDichThanhToan (maGiaoDich, maThamChieu, soTien, ngayThanhToan, loaiGiaoDich, trangThaiThanhToan, phuongThuc) VALUES (?, ?, ?, NOW(), 'ThanhToanGoiDichVu', 'ThanhCong', ?)");
        $stmtGD->execute(array($maGD, $hopDong['maHopDong'], $hopDong['tongTien'], $phuongThuc));
        $stmtUp = $pdo->prepare("UPDATE HopDongDichVu SET trangThai = 'DaThanhToan' WHERE maHopDong = ?");
        $stmtUp->execute(array($hopDong['maHopDong']));
        $stmtDN = $pdo->prepare("UPDATE DoanhNghiep SET trangThaiGianHang = 'DangHoatDong', maGoiHienTai = ? WHERE id = ?");
        $stmtDN->execute(array($hopDong['maGoi'], $idDN));
        addNotification($pdo, $idDN, "Kích hoạt gói dịch vụ", "Thanh toán gói " . $hopDong['tenGoi'] . " thành công. Gian hàng đã chuyển sang Đang hoạt động.");
        $pdo->commit();

        $alertType = 'success';
        $msgAlert = "<strong>Thanh toán gói dịch vụ thành công!</strong> Trạng thái gian hàng đã được cập nhật thành <strong>Đang hoạt động</strong>.";
        $step = 'done';
    }
}
?>

<div class="mb-3">
    <span class="badge bg-info text-dark">Đặc tả Use Case #2 - Actor: Doanh nghiệp & Cổng thanh toán</span>
    <h3 class="fw-bold mb-0">Thanh Toán Gói Dịch Vụ</h3>
</div>

<?php if ($msgAlert): ?>
    <div class="alert alert-<?php echo $alertType; ?> shadow-sm"><?php echo $msgAlert; ?></div>
<?php endif; ?>

<?php if ($step === 'failed_retry'): ?>
    <div class="card border-danger">
        <div class="card-body text-center py-4">
            <i class="bi bi-exclamation-triangle-fill text-danger fs-1"></i>
            <h5 class="fw-bold mt-2">Giao dịch không thành công</h5>
            <p class="text-muted">Trạng thái gian hàng của bạn vẫn được giữ nguyên (<code><?php echo htmlspecialchars($currentSellerInfo['trangThaiGianHang']); ?></code>).</p>
            <a href="uc2_thanh_toan_goi.php" class="btn btn-primary px-4">
                <i class="bi bi-arrow-repeat"></i> Thử lại (Quay lại Bước 1)
            </a>
        </div>
    </div>

<?php elseif ($step === 'gateway' && $hopDong): ?>
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-primary shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-shield-check"></i> CỔNG THANH TOÁN TRỰC TUYẾN (<?php echo htmlspecialchars($_SESSION['uc2_phuong_thuc']); ?>)</span>
                    <span class="badge bg-light text-primary">Mã HĐ: <?php echo htmlspecialchars($hopDong['maHopDong']); ?></span>
                </div>
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between border-bottom pb-3 mb-3">
                        <div>
                            <div class="text-muted small">Đơn vị thụ hưởng:</div>
                            <strong>Sàn TMĐT B2C XYZ - Phí <?php echo htmlspecialchars($hopDong['tenGoi']); ?></strong>
                        </div>
                        <div class="text-end">
                            <div class="text-muted small">Số tiền thanh toán:</div>
                            <strong class="text-danger fs-4"><?php echo formatVND($hopDong['tongTien']); ?></strong>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bước 5: Nhập Số thẻ / Tài khoản ví / Quét QR Code</label>
                            <input type="text" name="soThe" class="form-control" value="9704 1985 2619 1432 198" required>
                        </div>

                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-chat-dots-fill"></i> <strong>Bước 6 (Cổng thanh toán):</strong> Đã xác thực thông tin thẻ và gửi mã OTP về SĐT/Email của Doanh nghiệp. Mã OTP demo là: <code class="fw-bold fs-6">123456</code>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Bước 7: Nhập mã xác thực OTP <span class="text-danger">*</span></label>
                            <input type="text" name="otp_code" class="form-control form-control-lg text-center fw-bold" value="123456">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="gateway_action" value="confirm_success" class="btn btn-success btn-lg">
                                <i class="bi bi-check2-circle"></i> Bước 8: Xác nhận thanh toán (Thành công)
                            </button>
                            <div class="row g-2 mt-1">
                                <div class="col-md-4">
                                    <button type="submit" name="gateway_action" value="cancel" class="btn btn-outline-secondary w-100 btn-sm">
                                        Hủy giao dịch (Luồng 7.1)
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="gateway_action" value="fail_card" class="btn btn-outline-danger w-100 btn-sm">
                                        Giả lập Lỗi thẻ/Thiếu tiền (9.1)
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="gateway_action" value="network_error" class="btn btn-outline-warning text-dark w-100 btn-sm">
                                        Giả lập Đứt cáp & Đối soát (9.2)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($hopDong && $step !== 'done'): ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">Thông tin Yêu cầu Thanh toán Gói dịch vụ</div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tr><td class="text-muted">Mã hợp đồng:</td><td class="fw-bold"><?php echo htmlspecialchars($hopDong['maHopDong']); ?></td></tr>
                        <tr><td class="text-muted">Doanh nghiệp:</td><td><?php echo htmlspecialchars($currentSellerInfo['tenDoanhNghiep']); ?></td></tr>
                        <tr><td class="text-muted">Gói dịch vụ đăng ký:</td><td><span class="badge bg-primary"><?php echo htmlspecialchars($hopDong['tenGoi']); ?></span></td></tr>
                        <tr><td class="text-muted">Chu kỳ thanh toán:</td><td><?php echo htmlspecialchars($hopDong['chuKyThanhToan']); ?></td></tr>
                        <tr><td class="text-muted">Quyền Dynamic Pricing:</td><td><?php echo $hopDong['coDynamicPricing'] ? '<span class="text-success fw-bold">Có hỗ trợ</span>' : 'Không'; ?></td></tr>
                        <tr><td class="text-muted">Trạng thái yêu cầu:</td><td><span class="badge bg-warning text-dark"><?php echo htmlspecialchars($hopDong['trangThai']); ?></span></td></tr>
                        <tr class="border-top"><td class="fw-bold fs-5">Tổng số tiền:</td><td class="fw-bold fs-4 text-danger"><?php echo formatVND($hopDong['tongTien']); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">Bước 1 & 2: Chọn phương thức thanh toán</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-check p-3 border rounded mb-2">
                            <input class="form-check-input" type="radio" name="phuongThuc" id="pt1" value="VNPay" checked>
                            <label class="form-check-label fw-bold ms-2" for="pt1">Cổng thanh toán VNPay (QR / ATM nội địa)</label>
                        </div>
                        <div class="form-check p-3 border rounded mb-2">
                            <input class="form-check-input" type="radio" name="phuongThuc" id="pt2" value="MoMo">
                            <label class="form-check-label fw-bold ms-2" for="pt2">Ví điện tử MoMo</label>
                        </div>
                        <div class="form-check p-3 border rounded mb-4">
                            <input class="form-check-input" type="radio" name="phuongThuc" id="pt3" value="Thẻ quốc tế (Visa/Mastercard)">
                            <label class="form-check-label fw-bold ms-2" for="pt3">Thẻ quốc tế (Visa / Mastercard / JCB)</label>
                        </div>

                        <button type="submit" name="btn_init_payment" class="btn btn-primary btn-lg w-100">
                            <i class="bi bi-arrow-right-circle"></i> Xác nhận thanh toán (Chuyển sang Cổng TT)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="card">
        <div class="card-body text-center py-4">
            <i class="bi bi-check-circle-fill text-success fs-1"></i>
            <h5 class="fw-bold mt-2">Doanh nghiệp hiện không còn yêu cầu thanh toán gói nào đang chờ</h5>
            <p class="text-muted">Bạn có thể khởi tạo nhanh một yêu cầu "Chờ thanh toán" mới bên dưới để thực hành/demo lại Use Case 2:</p>
            <form method="POST" class="d-inline-flex gap-2 align-items-center">
                <input type="hidden" name="create_new_contract" value="1">
                <select name="maGoi" class="form-select form-select-sm" style="width:200px;">
                    <option value="GOI_NC">Gói Nâng Cao (1.500.000đ)</option>
                    <option value="GOI_DN">Gói Doanh Nghiệp (3.500.000đ)</option>
                </select>
                <select name="chuKy" class="form-select form-select-sm" style="width:120px;">
                    <option value="Tháng">Theo Tháng</option>
                    <option value="Năm">Theo Năm</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary">Tạo yêu cầu Chờ thanh toán mới</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>