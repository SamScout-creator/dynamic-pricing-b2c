<?php
// File: index.php
require_once 'includes/header.php';

// Xử lý nhanh: Tạo đơn hàng tạm kèm Price Lock 15 phút từ Trang chủ để test UC5
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_pricelock') {
    $maSP = $_POST['maSanPham'];
    $soLuong = max(1, (int)$_POST['soLuong']);

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM SanPham WHERE maSanPham = ? FOR UPDATE");
        $stmt->execute(array($maSP));
        $sp = $stmt->fetch();

        $tonKhaDung = $sp['soLuongTonKho'] - $sp['soLuongTamGiu'];
        if (!$sp || $sp['trangThaiHienThi'] !== 'HienThi' || $tonKhaDung < $soLuong) {
            throw new Exception("Sản phẩm không đủ tồn kho khả dụng để tạm giữ!");
        }

        // Tạm giữ tồn kho và tạo phiên khóa giá 15 phút
        $stmtUpd = $pdo->prepare("UPDATE SanPham SET soLuongTamGiu = soLuongTamGiu + ? WHERE maSanPham = ?");
        $stmtUpd->execute(array($soLuong, $maSP));

        $maDH = 'DH_' . rand(10000, 99999);
        $tongTien = $sp['giaBanHienTai'] * $soLuong;

        $stmtDH = $pdo->prepare("INSERT INTO DonHang (maDonHang, idKhachHang, idDoanhNghiep, ngayDat, hoTenNguoiNhan, soDTNhanHang, diaChiNhanHang, tongTien, trangThai, thoiDiemBatDauKhoaGia, thoiDiemHetHanKhoaGia, soLanThuThanhToan, daTamGiuKho) 
                                 VALUES (?, 'KH01', ?, NOW(), 'Nguyễn Văn Khách', '0988777666', '12 Nguyễn Văn Bảo, P.4, Q.Gò Vấp, TP.HCM', ?, 'DonHangTam', NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0, 1)");
        $stmtDH->execute(array($maDH, $sp['idDoanhNghiep'], $tongTien));

        $stmtCT = $pdo->prepare("INSERT INTO ChiTietDonHang (maChiTietDonHang, maDonHang, maSanPham, soLuong, donGia, thanhTien) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtCT->execute(array('CT_' . rand(10000, 99999), $maDH, $maSP, $soLuong, $sp['giaBanHienTai'], $tongTien));

        $pdo->commit();
        header("Location: uc5_thanh_toan_don_hang.php?maDonHang=$maDH");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMsg = $e->getMessage();
    }
}

$products = $pdo->query("
    SELECT sp.*, dn.tenDoanhNghiep, dn.trangThaiGianHang 
    FROM SanPham sp 
    JOIN DoanhNghiep dn ON sp.idDoanhNghiep = dn.id 
    WHERE sp.trangThaiHienThi = 'HienThi'
")->fetchAll();
?>

<div class="p-4 mb-4 rounded-4 text-white" style="background: linear-gradient(120deg, #1e3a8a, #3b82f6);">
    <div class="row align-items-center">
        <div class="col-lg-8">
            <span class="badge bg-warning text-dark mb-2">Đề tài: Hệ thống thông tin Quản lý Giá Động (Dynamic Pricing)</span>
            <h2 class="fw-bold mb-2">Sàn Thương Mại Điện Tử B2C XYZ</h2>
            <p class="mb-3 opacity-90">
                Tự động điều chỉnh giá bán theo nhu cầu thị trường & tồn kho trong biên độ <strong>[Giá sàn – Giá trần]</strong>, kết hợp cơ chế bảo vệ người mua <strong>Khóa giá (Price Lock) 15 phút</strong> tại bước thanh toán.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <a href="#san-pham" class="btn btn-light fw-semibold text-primary"><i class="bi bi-bag-check"></i> Mua sắm & Test Price Lock</a>
                <a href="#use-cases" class="btn btn-outline-light"><i class="bi bi-grid-3x3-gap"></i> Truy cập 6 Đặc tả Trọng tâm</a>
            </div>
        </div>
        <div class="col-lg-4 d-none d-lg-block text-end">
            <i class="bi bi-currency-exchange" style="font-size: 6rem; opacity: 0.3;"></i>
        </div>
    </div>
</div>

<?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
<?php endif; ?>

<h4 id="use-cases" class="fw-bold mb-3"><i class="bi bi-stars text-warning"></i> 6 Đặc Tả Use Case Trọng Tâm Của Dự Án</h4>
<div class="row g-3 mb-5">
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-primary">
            <div class="card-body">
                <span class="badge bg-primary-subtle text-primary mb-2">Actor: Doanh nghiệp</span>
                <h5 class="card-title fw-bold">1. Thiết lập giá sản phẩm</h5>
                <p class="card-text text-muted small">Kiểm tra ràng buộc <code>0 &lt; Giá sàn ≤ Giá cơ sở ≤ Giá trần</code>, kiểm tra gói dịch vụ Cơ bản/Nâng cao, bật/tắt Dynamic Pricing.</p>
                <a href="uc1_thiet_lap_gia.php" class="btn btn-sm btn-primary w-100">Mở chức năng UC1 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-info">
            <div class="card-body">
                <span class="badge bg-info-subtle text-info-emphasis mb-2">Actor: Doanh nghiệp + Cổng TT</span>
                <h5 class="card-title fw-bold">2. Thanh toán gói dịch vụ</h5>
                <p class="card-text text-muted small">Chọn VNPay/MoMo/Thẻ quốc tế, xác thực OTP trên Cổng thanh toán, kích hoạt gian hàng & đối soát tự động khi đứt cáp.</p>
                <a href="uc2_thanh_toan_goi.php" class="btn btn-sm btn-info text-white w-100">Mở chức năng UC2 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-danger">
            <div class="card-body">
                <span class="badge bg-danger-subtle text-danger mb-2">Actor: Khách hàng + Cổng TT</span>
                <h5 class="card-title fw-bold">3. Hủy đơn hàng (Khách hàng)</h5>
                <p class="card-text text-muted small">Kiểm tra đơn <code>Chờ xử lý</code>, chọn lý do hủy, cộng lại tồn kho, giảm số lượng đã bán và gọi Cổng thanh toán hoàn tiền.</p>
                <a href="uc3_huy_don_hang_kh.php" class="btn btn-sm btn-danger w-100">Mở chức năng UC3 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-warning">
            <div class="card-body">
                <span class="badge bg-warning-subtle text-warning-emphasis mb-2">Actor: Quản trị viên</span>
                <h5 class="card-title fw-bold">4. Xử lý vi phạm doanh nghiệp</h5>
                <p class="card-text text-muted small">Chế tài lũy tiến theo <code>soLanViPham</code>: Cảnh cáo 3 ngày (Lần 1) → Tạm khóa 7 ngày & ẩn SP (Lần 2) → Khóa vĩnh viễn (Lần 3+).</p>
                <a href="uc4_xu_ly_vi_pham.php" class="btn btn-sm btn-warning w-100">Mở chức năng UC4 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-success">
            <div class="card-body">
                <span class="badge bg-success-subtle text-success mb-2">Actor: Khách hàng + Cổng TT</span>
                <h5 class="card-title fw-bold">5. Thanh toán đơn hàng</h5>
                <p class="card-text text-muted small">Đồng hồ đếm ngược <code>Price Lock 15 phút</code>, giới hạn thử lại tối đa 3 lần, giải phóng kho tạm giữ hoặc tự động hoàn tiền nếu quá hạn.</p>
                <a href="uc5_thanh_toan_don_hang.php" class="btn btn-sm btn-success w-100">Mở chức năng UC5 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 border-start border-4 border-secondary">
            <div class="card-body">
                <span class="badge bg-secondary-subtle text-secondary mb-2">Actor: Quản trị viên</span>
                <h5 class="card-title fw-bold">6. Cấu hình quy tắc giá động</h5>
                <p class="card-text text-muted small">Quản lý danh sách ngày lễ (kiểm tra trùng ngày, biên độ ≤ 10%) và cấu hình ngưỡng xả hàng tồn kho toàn sàn.</p>
                <a href="uc6_cau_hinh_gia_dong.php" class="btn btn-sm btn-secondary w-100">Mở chức năng UC6 <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-3" id="san-pham">
    <h4 class="fw-bold mb-0"><i class="bi bi-shop text-primary"></i> Sản Phẩm Đang Kinh Doanh Trên Sàn</h4>
    <span class="text-muted small">Giá hiển thị theo thời gian thực từ Dynamic Pricing Engine</span>
</div>

<div class="row g-4">
    <?php foreach ($products as $p): 
        $khaDung = $p['soLuongTonKho'] - $p['soLuongTamGiu'];
    ?>
    <div class="col-md-6 col-lg-3">
        <div class="card h-100">
            <img src="<?php echo htmlspecialchars($p['hinhAnh']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($p['tenSanPham']); ?>" style="height: 180px; object-fit: cover;">
            <div class="card-body d-flex flex-column">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($p['danhMuc']); ?></span>
                    <?php if ($p['trangThaiDynamicPricing']): ?>
                        <span class="badge badge-dp"><i class="bi bi-lightning-charge-fill"></i> Dynamic Pricing</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Giá cố định</span>
                    <?php endif; ?>
                </div>
                <h6 class="card-title fw-bold"><?php echo htmlspecialchars($p['tenSanPham']); ?></h6>
                <p class="text-muted small mb-2"><i class="bi bi-building"></i> <?php echo htmlspecialchars($p['tenDoanhNghiep']); ?></p>
                
                <div class="bg-light p-2 rounded mb-3 small">
                    <div class="d-flex justify-content-between">
                        <span>Giá hiện hành:</span>
                        <strong class="text-danger fs-6"><?php echo formatVND($p['giaBanHienTai']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between text-muted">
                        <span>Giá cơ sở:</span>
                        <span><?php echo formatVND($p['giaCoSo']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size:0.78rem;">
                        <span>Khung [Sàn - Trần]:</span>
                        <span><?php echo formatVND($p['giaSan']); ?> - <?php echo formatVND($p['giaTran']); ?></span>
                    </div>
                    <div class="d-flex justify-content-between text-muted mt-1 pt-1 border-top">
                        <span>Tồn khả dụng:</span>
                        <strong><?php echo $khaDung; ?> SP <small class="text-warning">(Tạm giữ: <?php echo $p['soLuongTamGiu']; ?>)</small></strong>
                    </div>
                </div>

                <div class="mt-auto">
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="action" value="quick_pricelock">
                        <input type="hidden" name="maSanPham" value="<?php echo htmlspecialchars($p['maSanPham']); ?>">
                        <input type="number" name="soLuong" value="1" min="1" max="<?php echo max(1, $khaDung); ?>" class="form-control form-control-sm" style="width: 70px;">
                        <button type="submit" class="btn btn-sm btn-success w-100" <?php echo ($khaDung <= 0) ? 'disabled' : ''; ?>>
                            <i class="bi bi-lock-fill"></i> Mua & Khóa giá 15p
                        </button>
                    </form>
                    <a href="uc1_thiet_lap_gia.php?maSanPham=<?php echo urlencode($p['maSanPham']); ?>" class="btn btn-sm btn-outline-primary w-100 mt-2">
                        <i class="bi bi-sliders"></i> Thiết lập khung giá (UC1)
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php require_once 'includes/footer.php'; ?>