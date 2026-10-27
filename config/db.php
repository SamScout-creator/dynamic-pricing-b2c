<?php
// File: config/db.php (Tương thích 100% mọi phiên bản PHP 5.3+ / 7.x / 8.x)
if (session_id() == '') {
    session_start();
}
date_default_timezone_set('Asia/Ho_Chi_Minh');

$host = 'localhost';
$dbname = 'dynamic_pricing_b2c';
$username = 'root';
$password = '';

try {
    // Kết nối MySQL Server trước để tự động tạo Database nếu chưa có
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("SET NAMES utf8");

    // Tự động tạo database và chọn database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Kiểm tra xem bảng GoiDichVu đã tồn tại chưa, nếu chưa thì tự động tạo toàn bộ 13 bảng & dữ liệu mẫu
    $checkTable = $pdo->query("SHOW TABLES LIKE 'GoiDichVu'")->fetch();
    if (!$checkTable) {
        initDatabaseAndSeed($pdo);
    }
} catch (PDOException $e) {
    die("<div style='padding:20px;font-family:sans-serif;background:#fee2e2;color:#991b1b;border-radius:8px;margin:20px;'>
        <h3>Lỗi kết nối Cơ sở dữ liệu (WAMP / MySQL)</h3>
        <p>Chi tiết: " . htmlspecialchars($e->getMessage()) . "</p>
    </div>");
}

// Khởi tạo phiên giả lập mặc định cho 3 Actor (Doanh nghiệp, Khách hàng, Quản trị viên)
if (!isset($_SESSION['current_seller'])) {
    $_SESSION['current_seller'] = 'DN01';
}
if (!isset($_SESSION['current_customer'])) {
    $_SESSION['current_customer'] = 'KH01';
}
if (!isset($_SESSION['current_admin'])) {
    $_SESSION['current_admin'] = 'ADMIN01';
}

if (isset($_GET['switch_seller'])) {
    $_SESSION['current_seller'] = $_GET['switch_seller'];
    $parts = explode('?', $_SERVER["REQUEST_URI"]);
    header("Location: " . $parts[0]);
    exit;
}

function formatVND($number) {
    return number_format((float)$number, 0, ',', '.') . ' ₫';
}

function addNotification($pdo, $nguoiNhan, $tieuDe, $noiDung) {
    $stmt = $pdo->prepare("INSERT INTO ThongBao (nguoiNhan, tieuDe, noiDung, thoiGian) VALUES (?, ?, ?, NOW())");
    $stmt->execute(array($nguoiNhan, $tieuDe, $noiDung));
}

function initDatabaseAndSeed($pdo) {
    $sqls = array(
        "CREATE TABLE IF NOT EXISTS GoiDichVu (
            maGoi VARCHAR(20) PRIMARY KEY,
            tenGoi VARCHAR(100) NOT NULL,
            gioiHanSanPham INT NOT NULL,
            coDynamicPricing TINYINT(1) NOT NULL DEFAULT 0,
            goiDichVuThang DECIMAL(15,2) NOT NULL,
            goiDichVuNam DECIMAL(15,2) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS DoanhNghiep (
            id VARCHAR(20) PRIMARY KEY,
            tenDangNhap VARCHAR(50) NOT NULL,
            tenDoanhNghiep VARCHAR(150) NOT NULL,
            matKhau VARCHAR(255) NOT NULL,
            giayPhepKinhDoanh VARCHAR(255),
            trangThaiHoSo VARCHAR(50) NOT NULL DEFAULT 'DaPheDuyet',
            trangThaiGianHang VARCHAR(50) NOT NULL DEFAULT 'DangHoatDong',
            lyDoTuChoi TEXT,
            soLanViPham INT NOT NULL DEFAULT 0,
            maSoThue VARCHAR(50) NOT NULL,
            soDT VARCHAR(20) NOT NULL,
            maGoiHienTai VARCHAR(20)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS KhachHang (
            id VARCHAR(20) PRIMARY KEY,
            tenDangNhap VARCHAR(50) NOT NULL,
            hoTen VARCHAR(100) NOT NULL,
            matKhau VARCHAR(255) NOT NULL,
            soDT VARCHAR(20) NOT NULL,
            diaChiGiaoHang TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS QuanTriVien (
            id VARCHAR(20) PRIMARY KEY,
            tenDangNhap VARCHAR(50) NOT NULL,
            matKhau VARCHAR(255) NOT NULL,
            soDT VARCHAR(20),
            hoTen VARCHAR(100) NOT NULL,
            chucVu VARCHAR(100),
            trangThai VARCHAR(50) DEFAULT 'HoatDong'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS SanPham (
            maSanPham VARCHAR(20) PRIMARY KEY,
            idDoanhNghiep VARCHAR(20) NOT NULL,
            tenSanPham VARCHAR(200) NOT NULL,
            moTa TEXT,
            danhMuc VARCHAR(100) NOT NULL,
            hinhAnh VARCHAR(255),
            soLuongTonKho INT NOT NULL DEFAULT 0,
            soLuongTamGiu INT NOT NULL DEFAULT 0,
            giaCoSo DECIMAL(15,2) NOT NULL DEFAULT 0,
            giaSan DECIMAL(15,2) NOT NULL DEFAULT 0,
            giaTran DECIMAL(15,2) NOT NULL DEFAULT 0,
            giaBanHienTai DECIMAL(15,2) NOT NULL DEFAULT 0,
            trangThaiDynamicPricing TINYINT(1) NOT NULL DEFAULT 0,
            trangThaiHienThi VARCHAR(50) NOT NULL DEFAULT 'HienThi',
            luotXem INT NOT NULL DEFAULT 0,
            luotThemGio INT NOT NULL DEFAULT 0,
            soLuongDaBan INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS HopDongDichVu (
            maHopDong VARCHAR(20) PRIMARY KEY,
            idDoanhNghiep VARCHAR(20) NOT NULL,
            maGoi VARCHAR(20) NOT NULL,
            ngayBatDau DATETIME,
            ngayKetThuc DATETIME,
            chuKyThanhToan VARCHAR(20) NOT NULL,
            tongTien DECIMAL(15,2) NOT NULL,
            trangThai VARCHAR(50) NOT NULL DEFAULT 'ChoThanhToan'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS DonHang (
            maDonHang VARCHAR(20) PRIMARY KEY,
            idKhachHang VARCHAR(20) NOT NULL,
            idDoanhNghiep VARCHAR(20) NOT NULL,
            ngayDat DATETIME NOT NULL,
            hoTenNguoiNhan VARCHAR(100),
            soDTNhanHang VARCHAR(20),
            diaChiNhanHang TEXT,
            thongTinNhanHang TEXT,
            phuongThucThanhToan VARCHAR(50),
            tongTien DECIMAL(15,2) NOT NULL,
            trangThai VARCHAR(50) NOT NULL DEFAULT 'DonHangTam',
            lyDoHuy TEXT,
            thoiDiemBatDauKhoaGia DATETIME,
            thoiDiemHetHanKhoaGia DATETIME,
            soLanThuThanhToan INT NOT NULL DEFAULT 0,
            daTamGiuKho TINYINT(1) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS ChiTietDonHang (
            maChiTietDonHang VARCHAR(20) PRIMARY KEY,
            maDonHang VARCHAR(20) NOT NULL,
            maSanPham VARCHAR(20) NOT NULL,
            soLuong INT NOT NULL,
            donGia DECIMAL(15,2) NOT NULL,
            thanhTien DECIMAL(15,2) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS GiaoDichThanhToan (
            maGiaoDich VARCHAR(30) PRIMARY KEY,
            maThamChieu VARCHAR(20) NOT NULL,
            soTien DECIMAL(15,2) NOT NULL,
            ngayThanhToan DATETIME NOT NULL,
            loaiGiaoDich VARCHAR(50) NOT NULL,
            trangThaiThanhToan VARCHAR(50) NOT NULL,
            phuongThuc VARCHAR(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS HoSoViPham (
            maViPham VARCHAR(20) PRIMARY KEY,
            idDoanhNghiep VARCHAR(20) NOT NULL,
            loaiViPham VARCHAR(150) NOT NULL,
            moTaSaiPham TEXT NOT NULL,
            chungCuLog TEXT,
            nguonBaoCao VARCHAR(100),
            ngayBaoCao DATE NOT NULL,
            lyDoViPham TEXT,
            hinhThucXuLy VARCHAR(50),
            ngayXuLy DATETIME,
            thoiHanKhacPhuc INT DEFAULT 0,
            lanViPham INT DEFAULT 0,
            trangThaiHoSoViPham VARCHAR(50) NOT NULL DEFAULT 'ChoXuLy',
            baoCaoKhacPhuc TEXT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS QuyTacGiaDong (
            maQuyTac INT AUTO_INCREMENT PRIMARY KEY,
            tenQuyTac VARCHAR(100) NOT NULL,
            loaiQuyTac VARCHAR(50) NOT NULL,
            mucDieuChinh DOUBLE NOT NULL,
            ngayBatDau DATE NULL,
            ngayKetThuc DATE NULL,
            soNgayKhongDon INT NULL,
            soNgayTinh INT NULL,
            tyLeTonKhoConLai DOUBLE NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS LichSuThayDoiGia (
            maLichSu INT AUTO_INCREMENT PRIMARY KEY,
            maSanPham VARCHAR(20) NOT NULL,
            giaTruoc DECIMAL(15,2) NOT NULL,
            giaSau DECIMAL(15,2) NOT NULL,
            thoiDiemThayDoi DATETIME NOT NULL,
            lyDoThayDoi VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "CREATE TABLE IF NOT EXISTS ThongBao (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nguoiNhan VARCHAR(50) NOT NULL,
            tieuDe VARCHAR(200) NOT NULL,
            noiDung TEXT NOT NULL,
            thoiGian DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8",

        "INSERT INTO GoiDichVu VALUES
        ('GOI_CB', 'Gói Cơ Bản', 50, 0, 500000, 5000000),
        ('GOI_NC', 'Gói Nâng Cao', 500, 1, 1500000, 15000000),
        ('GOI_DN', 'Gói Doanh Nghiệp', 999999, 1, 3500000, 35000000)",

        "INSERT INTO DoanhNghiep VALUES
        ('DN01', 'seller_nangcao', 'Công ty TNHH Công Nghệ TechZone (Gói Nâng Cao)', '123456', 'GPKD_01.pdf', 'DaPheDuyet', 'DangHoatDong', NULL, 0, '0312345001', '0901111222', 'GOI_NC'),
        ('DN02', 'seller_coban', 'Cửa Hàng Phụ Kiện BasicShop (Gói Cơ Bản)', '123456', 'GPKD_02.pdf', 'DaPheDuyet', 'TamNgung', NULL, 1, '0312345002', '0903333444', 'GOI_CB'),
        ('DN03', 'seller_vipham2', 'Gia Dụng Thông Minh SmartHome (Đã vi phạm 2 lần)', '123456', 'GPKD_03.pdf', 'DaPheDuyet', 'DangHoatDong', NULL, 2, '0312345003', '0905555666', 'GOI_NC'),
        ('DN04', 'seller_khacphuc', 'Thời Trang Việt VinaStyle (Đang khắc phục lần 1)', '123456', 'GPKD_04.pdf', 'DaPheDuyet', 'DangHoatDong', NULL, 1, '0312345004', '0907777888', 'GOI_NC')",

        "INSERT INTO KhachHang VALUES
        ('KH01', 'khachhang01', 'Nguyễn Văn Khách', '123456', '0988777666', '12 Nguyễn Văn Bảo, Phường 4, Quận Gò Vấp, TP.HCM')",

        "INSERT INTO QuanTriVien VALUES
        ('ADMIN01', 'admin', '123456', '0911222333', 'Lê Ngọc An (Quản trị viên sàn)', 'Trưởng ban Quản trị', 'HoatDong')",

        "INSERT INTO SanPham VALUES
        ('SP01', 'DN01', 'Tai nghe Bluetooth Chống Ồn Sony WH-1000XM5', 'Tai nghe chống ồn chủ động thế hệ mới, pin 30 giờ.', 'Thiết bị âm thanh', 'https://placehold.co/400x300/2563eb/ffffff?text=Sony+WH-1000XM5', 25, 2, 7500000, 6800000, 8500000, 7650000, 1, 'HienThi', 420, 65, 18),
        ('SP02', 'DN01', 'Bàn phím Cơ Không Dây Keychron K8 Pro', 'Bàn phím cơ QMK/VIA khung nhôm nguyên khối.', 'Phụ kiện máy tính', 'https://placehold.co/400x300/059669/ffffff?text=Keychron+K8+Pro', 40, 0, 2400000, 2000000, 2800000, 2400000, 1, 'HienThi', 210, 30, 12),
        ('SP03', 'DN02', 'Chuột Không Dây Logitech M331 Silent', 'Chuột văn phòng giảm tiếng ồn click 90%.', 'Phụ kiện máy tính', 'https://placehold.co/400x300/d97706/ffffff?text=Logitech+M331', 50, 0, 350000, 300000, 420000, 350000, 0, 'HienThi', 95, 10, 5),
        ('SP04', 'DN03', 'Robot Hút Bụi Lau Nhà Dreame L10s Ultra', 'Tự động giặt giẻ, sấy khô bằng khí nóng.', 'Điện gia dụng', 'https://placehold.co/400x300/7c3aed/ffffff?text=Dreame+L10s', 15, 0, 14000000, 12500000, 16000000, 14500000, 1, 'HienThi', 310, 45, 9)",

        "INSERT INTO HopDongDichVu VALUES
        ('HD_DV_01', 'DN02', 'GOI_NC', NOW(), DATE_ADD(NOW(), INTERVAL 1 MONTH), 'Tháng', 1500000, 'ChoThanhToan'),
        ('HD_DV_02', 'DN01', 'GOI_DN', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'Năm', 35000000, 'ChoThanhToan')",

        "INSERT INTO DonHang VALUES
        ('DH_CHUXULY_01', 'KH01', 'DN01', DATE_SUB(NOW(), INTERVAL 2 HOUR), 'Nguyễn Văn Khách', '0988777666', '12 Nguyễn Văn Bảo, Gò Vấp, TP.HCM', 'Nguyễn Văn Khách - 0988777666 - 12 Nguyễn Văn Bảo, Gò Vấp, TP.HCM', 'VNPay', 2400000, 'ChoXuLy', NULL, DATE_SUB(NOW(), INTERVAL 130 MINUTE), DATE_SUB(NOW(), INTERVAL 115 MINUTE), 1, 0),
        ('DH_DANGGIAO_02', 'KH01', 'DN01', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Nguyễn Văn Khách', '0988777666', '12 Nguyễn Văn Bảo, Gò Vấp, TP.HCM', 'Nguyễn Văn Khách - 0988777666 - 12 Nguyễn Văn Bảo, Gò Vấp, TP.HCM', 'MoMo', 350000, 'DangGiao', NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 1, 0),
        ('DH_PRICELOCK_03', 'KH01', 'DN01', NOW(), 'Nguyễn Văn Khách', '0988777666', '12 Nguyễn Văn Bảo, P.4, Q.Gò Vấp, TP.HCM', NULL, 'VNPay', 15300000, 'DonHangTam', NULL, NOW(), DATE_ADD(NOW(), INTERVAL 15 MINUTE), 0, 1)",

        "INSERT INTO ChiTietDonHang VALUES
        ('CTDH01', 'DH_CHUXULY_01', 'SP02', 1, 2400000, 2400000),
        ('CTDH02', 'DH_DANGGIAO_02', 'SP03', 1, 350000, 350000),
        ('CTDH03', 'DH_PRICELOCK_03', 'SP01', 2, 7650000, 15300000)",

        "INSERT INTO HoSoViPham VALUES
        ('VP01', 'DN01', 'Thao túng khung giá động', 'Thiết lập giá trần cao bất thường trước đợt sale rồi hạ giá ảo để đánh lừa thuật toán.', 'Log hệ thống: SP01 đổi khung giá 6 lần trong 15 phút.', 'Hệ thống Giám sát giá động', CURDATE(), NULL, NULL, NULL, 0, 0, 'ChoXuLy', NULL),
        ('VP02', 'DN02', 'Gian lận đơn hàng (Buff đơn ảo)', 'Tạo 35 đơn hàng ảo từ cùng 1 địa chỉ IP trong 10 phút nhằm kích hoạt ngưỡng tăng giá tự động.', 'IP 113.161.72.15 lặp lại 35 giao dịch liên tiếp.', 'Hệ thống Giám sát giá động', CURDATE(), NULL, NULL, NULL, 0, 0, 'ChoXuLy', NULL),
        ('VP03', 'DN03', 'Tái phạm đăng sản phẩm cấm', 'Tái phạm lần thứ 3 hành vi đăng bán thiết bị thu phát sóng không có giấy phép hợp chuẩn.', 'Mã kiểm duyệt #KD992 - Đã cảnh cáo và tạm khóa 7 ngày trước đó.', 'Kiểm duyệt viên sản phẩm', CURDATE(), NULL, NULL, NULL, 0, 0, 'ChoXuLy', NULL),
        ('VP04', 'DN04', 'Thao túng khung giá động', 'Đặt Giá sàn và Giá trần chênh lệch sai quy định ngành hàng thời trang.', 'Đã nhận cảnh cáo lần 1 ngày hôm qua.', 'Hệ thống Giám sát giá động', DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'Yêu cầu điều chỉnh lại Giá trần chuẩn trong 3 ngày.', 'CanhCao', DATE_SUB(NOW(), INTERVAL 1 DAY), 3, 1, 'DangKhacPhuc', 'Doanh nghiệp đã điều chỉnh lại Giá sàn = 200.000đ, Giá trần = 350.000đ đúng quy định và cam kết không tái phạm.')",

        "INSERT INTO QuyTacGiaDong (tenQuyTac, loaiQuyTac, mucDieuChinh, ngayBatDau, ngayKetThuc, soNgayKhongDon, soNgayTinh, tyLeTonKhoConLai) VALUES
        ('Quy tắc xả kho mặc định toàn sàn', 'TonKho', 5.0, NULL, NULL, 15, 10, 80.0),
        ('Siêu Sale Ngày Đôi 11.11', 'NgayLe', 8.0, '2026-11-10', '2026-11-12', NULL, NULL, NULL),
        ('Lễ Giáng Sinh & Cuối Năm', 'NgayLe', 6.5, '2026-12-24', '2026-12-26', NULL, NULL, NULL)"
    );

    foreach ($sqls as $q) {
        $pdo->exec($q);
    }
}
?>