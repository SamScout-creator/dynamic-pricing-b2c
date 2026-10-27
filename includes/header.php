<?php
// File: includes/header.php
require_once dirname(__FILE__) . '/../config/db.php';

$stmtSeller = $pdo->prepare("SELECT dn.*, g.tenGoi, g.coDynamicPricing FROM DoanhNghiep dn LEFT JOIN GoiDichVu g ON dn.maGoiHienTai = g.maGoi WHERE dn.id = ?");
$stmtSeller->execute(array($_SESSION['current_seller']));
$currentSellerInfo = $stmtSeller->fetch();

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sàn TMĐT B2C - Hệ Thống Quản Lý Giá Động (Dynamic Pricing)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
        .navbar-brand { font-weight: 800; letter-spacing: -0.5px; }
        .uc-nav .nav-link { font-size: 0.9rem; font-weight: 600; border-radius: 6px; margin-right: 4px; padding: 6px 12px; }
        .uc-nav .nav-link.active { background-color: #2563eb; color: #fff !important; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.05); }
        .badge-dp { background: linear-gradient(135deg, #2563eb, #7c3aed); color: white; }
        .actor-bar { background: #1e293b; color: #e2e8f0; font-size: 0.85rem; }
    </style>
</head>
<body>

<div class="actor-bar py-2 border-bottom border-secondary">
    <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <span class="badge bg-warning text-dark me-2"><i class="bi bi-sliders"></i> Chế độ Demo Đồ Án</span>
            Đang đóng vai Doanh nghiệp: 
            <strong class="text-info"><?php echo htmlspecialchars($currentSellerInfo['tenDoanhNghiep']); ?></strong>
            (Trạng thái: <span class="badge bg-secondary"><?php echo htmlspecialchars($currentSellerInfo['trangThaiGianHang']); ?></span>)
        </div>
        <div class="d-flex align-items-center gap-2">
            <span>Đổi nhanh tài khoản Doanh nghiệp:</span>
            <a href="?switch_seller=DN01" class="btn btn-sm <?php echo ($_SESSION['current_seller']=='DN01') ? 'btn-primary' : 'btn-outline-light'; ?> py-0">DN01 (Gói Nâng cao - Đang hoạt động)</a>
            <a href="?switch_seller=DN02" class="btn btn-sm <?php echo ($_SESSION['current_seller']=='DN02') ? 'btn-primary' : 'btn-outline-light'; ?> py-0">DN02 (Gói Cơ bản - Tạm ngưng)</a>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-xl navbar-light bg-white border-bottom sticky-top py-2">
    <div class="container">
        <a class="navbar-brand text-primary d-flex align-items-center gap-2" href="index.php">
            <i class="bi bi-graph-up-arrow fs-4"></i>
            <span>XYZ B2C <small class="badge badge-dp fw-normal" style="font-size:0.7rem;">Dynamic Pricing</small></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav ms-auto uc-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='index.php')?'active':''; ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Trang chủ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc1_thiet_lap_gia.php')?'active':''; ?>" href="uc1_thiet_lap_gia.php">
                        <i class="bi bi-sliders2"></i> UC1: Thiết lập giá SP
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc2_thanh_toan_goi.php')?'active':''; ?>" href="uc2_thanh_toan_goi.php">
                        <i class="bi bi-credit-card-2-front"></i> UC2: Thanh toán gói DV
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc5_thanh_toan_don_hang.php')?'active':''; ?>" href="uc5_thanh_toan_don_hang.php">
                        <i class="bi bi-shield-lock"></i> UC5: Thanh toán đơn (Price Lock)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc3_huy_don_hang_kh.php')?'active':''; ?>" href="uc3_huy_don_hang_kh.php">
                        <i class="bi bi-x-octagon"></i> UC3: Hủy đơn (KH)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc4_xu_ly_vi_pham.php')?'active':''; ?>" href="uc4_xu_ly_vi_pham.php">
                        <i class="bi bi-shield-exclamation"></i> UC4: Xử lý vi phạm DN
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage=='uc6_cau_hinh_gia_dong.php')?'active':''; ?>" href="uc6_cau_hinh_gia_dong.php">
                        <i class="bi bi-gear-wide-connected"></i> UC6: Quy tắc giá động
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-4">