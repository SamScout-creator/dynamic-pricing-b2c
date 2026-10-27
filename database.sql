-- File: database.sql (Tương thích MySQL 5.5+ trên WAMP)
CREATE DATABASE IF NOT EXISTS `dynamic_pricing_b2c` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;
USE `dynamic_pricing_b2c`;

CREATE TABLE IF NOT EXISTS GoiDichVu (
    maGoi VARCHAR(20) PRIMARY KEY,
    tenGoi VARCHAR(100) NOT NULL,
    gioiHanSanPham INT NOT NULL,
    coDynamicPricing TINYINT(1) NOT NULL DEFAULT 0,
    goiDichVuThang DECIMAL(15,2) NOT NULL,
    goiDichVuNam DECIMAL(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS DoanhNghiep (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS KhachHang (
    id VARCHAR(20) PRIMARY KEY,
    tenDangNhap VARCHAR(50) NOT NULL,
    hoTen VARCHAR(100) NOT NULL,
    matKhau VARCHAR(255) NOT NULL,
    soDT VARCHAR(20) NOT NULL,
    diaChiGiaoHang TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS QuanTriVien (
    id VARCHAR(20) PRIMARY KEY,
    tenDangNhap VARCHAR(50) NOT NULL,
    matKhau VARCHAR(255) NOT NULL,
    soDT VARCHAR(20),
    hoTen VARCHAR(100) NOT NULL,
    chucVu VARCHAR(100),
    trangThai VARCHAR(50) DEFAULT 'HoatDong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS SanPham (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS HopDongDichVu (
    maHopDong VARCHAR(20) PRIMARY KEY,
    idDoanhNghiep VARCHAR(20) NOT NULL,
    maGoi VARCHAR(20) NOT NULL,
    ngayBatDau DATETIME,
    ngayKetThuc DATETIME,
    chuKyThanhToan VARCHAR(20) NOT NULL,
    tongTien DECIMAL(15,2) NOT NULL,
    trangThai VARCHAR(50) NOT NULL DEFAULT 'ChoThanhToan'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS DonHang (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ChiTietDonHang (
    maChiTietDonHang VARCHAR(20) PRIMARY KEY,
    maDonHang VARCHAR(20) NOT NULL,
    maSanPham VARCHAR(20) NOT NULL,
    soLuong INT NOT NULL,
    donGia DECIMAL(15,2) NOT NULL,
    thanhTien DECIMAL(15,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS GiaoDichThanhToan (
    maGiaoDich VARCHAR(30) PRIMARY KEY,
    maThamChieu VARCHAR(20) NOT NULL,
    soTien DECIMAL(15,2) NOT NULL,
    ngayThanhToan DATETIME NOT NULL,
    loaiGiaoDich VARCHAR(50) NOT NULL,
    trangThaiThanhToan VARCHAR(50) NOT NULL,
    phuongThuc VARCHAR(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS HoSoViPham (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS QuyTacGiaDong (
    maQuyTac INT AUTO_INCREMENT PRIMARY KEY,
    tenQuyTac VARCHAR(100) NOT NULL,
    loaiQuyTac VARCHAR(50) NOT NULL,
    mucDieuChinh DOUBLE NOT NULL,
    ngayBatDau DATE NULL,
    ngayKetThuc DATE NULL,
    soNgayKhongDon INT NULL,
    soNgayTinh INT NULL,
    tyLeTonKhoConLai DOUBLE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS LichSuThayDoiGia (
    maLichSu INT AUTO_INCREMENT PRIMARY KEY,
    maSanPham VARCHAR(20) NOT NULL,
    giaTruoc DECIMAL(15,2) NOT NULL,
    giaSau DECIMAL(15,2) NOT NULL,
    thoiDiemThayDoi DATETIME NOT NULL,
    lyDoThayDoi VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS ThongBao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nguoiNhan VARCHAR(50) NOT NULL,
    tieuDe VARCHAR(200) NOT NULL,
    noiDung TEXT NOT NULL,
    thoiGian DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;