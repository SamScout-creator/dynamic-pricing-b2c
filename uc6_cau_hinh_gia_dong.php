<?php
// File: uc6_cau_hinh_gia_dong.php
require_once 'includes/header.php';

$msgSuccess = '';
$msgError = '';
$fieldErrors = array();

$stmtTK = $pdo->query("SELECT * FROM QuyTacGiaDong WHERE loaiQuyTac = 'TonKho' ORDER BY maQuyTac DESC LIMIT 1");
$rowTK = $stmtTK->fetch();
$quyTacTonKho = $rowTK ? $rowTK : array(
    'soNgayKhongDon' => 15,
    'soNgayTinh' => 10,
    'tyLeTonKhoConLai' => 80,
    'mucDieuChinh' => 5
);

$formNgayLe = array(
    'tenQuyTac' => isset($_POST['tenQuyTac']) ? $_POST['tenQuyTac'] : '',
    'ngayBatDau' => isset($_POST['ngayBatDau']) ? $_POST['ngayBatDau'] : '',
    'ngayKetThuc' => isset($_POST['ngayKetThuc']) ? $_POST['ngayKetThuc'] : '',
    'mucTangGia' => isset($_POST['mucTangGia']) ? $_POST['mucTangGia'] : ''
);
$formTonKho = array(
    'soNgayKhongDon' => isset($_POST['soNgayKhongDon']) ? $_POST['soNgayKhongDon'] : $quyTacTonKho['soNgayKhongDon'],
    'soNgayTinh' => isset($_POST['soNgayTinh']) ? $_POST['soNgayTinh'] : $quyTacTonKho['soNgayTinh'],
    'tyLeTonKhoConLai' => isset($_POST['tyLeTonKhoConLai']) ? $_POST['tyLeTonKhoConLai'] : $quyTacTonKho['tyLeTonKhoConLai'],
    'mucGiamGia' => isset($_POST['mucGiamGia']) ? $_POST['mucGiamGia'] : $quyTacTonKho['mucDieuChinh']
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['btn_cancel'])) {
        $formNgayLe = array('tenQuyTac' => '', 'ngayBatDau' => '', 'ngayKetThuc' => '', 'mucTangGia' => '');
        $formTonKho = array(
            'soNgayKhongDon' => $quyTacTonKho['soNgayKhongDon'],
            'soNgayTinh' => $quyTacTonKho['soNgayTinh'],
            'tyLeTonKhoConLai' => $quyTacTonKho['tyLeTonKhoConLai'],
            'mucGiamGia' => $quyTacTonKho['mucDieuChinh']
        );
        $msgSuccess = "Đã hủy các thay đổi chưa lưu và khôi phục lại giá trị cấu hình đang dùng.";
    } elseif (isset($_POST['delete_holiday_id'])) {
        $idXoa = (int)$_POST['delete_holiday_id'];
        try {
            $stmtDel = $pdo->prepare("DELETE FROM QuyTacGiaDong WHERE maQuyTac = ? AND loaiQuyTac = 'NgayLe'");
            $stmtDel->execute(array($idXoa));
            $msgSuccess = "Đã xóa ngày lễ khỏi danh sách. Các thông tin bạn đang nhập dở bên dưới vẫn được giữ nguyên.";
        } catch (Exception $e) {
            $msgError = "Không thể xóa. Vui lòng thử lại sau.";
        }
    } elseif (isset($_POST['btn_save_config'])) {
        $tenNL  = trim($formNgayLe['tenQuyTac']);
        $bdNL   = trim($formNgayLe['ngayBatDau']);
        $ktNL   = trim($formNgayLe['ngayKetThuc']);
        $tangNL = trim($formNgayLe['mucTangGia']);

        $coNhapNgayLe = ($tenNL !== '' || $bdNL !== '' || $ktNL !== '' || $tangNL !== '');

        if ($coNhapNgayLe) {
            if ($tenNL === '' || $bdNL === '' || $ktNL === '' || $tangNL === '') {
                $fieldErrors['ngayLe'] = "Nếu muốn thêm ngày lễ mới, vui lòng điền đầy đủ cả 4 trường (Tên, Ngày bắt đầu, Ngày kết thúc, % tăng giá).";
            } else {
                if (strlen($tenNL) > 150) {
                    $fieldErrors['tenQuyTac'] = "Tên ngày lễ không được vượt quá 100 ký tự.";
                }
                if ($bdNL < date('Y-m-d')) {
                    $fieldErrors['ngayBatDau'] = "Ngày bắt đầu không được trước ngày hiện tại (Hôm nay: " . date('Y-m-d') . " vẫn hợp lệ).";
                }
                if ($ktNL < $bdNL) {
                    $fieldErrors['ngayKetThuc'] = "Ngày kết thúc không được trước ngày bắt đầu.";
                }
                if (!is_numeric($tangNL) || (float)$tangNL <= 0 || (float)$tangNL > 10) {
                    $fieldErrors['mucTangGia'] = "Phần trăm tăng giá phải lớn hơn 0% và tối đa 10%.";
                }

                if (empty($fieldErrors)) {
                    $stmtOverlap = $pdo->prepare("
                        SELECT tenQuyTac, ngayBatDau, ngayKetThuc 
                        FROM QuyTacGiaDong 
                        WHERE loaiQuyTac = 'NgayLe' AND NOT (ngayKetThuc < ? OR ngayBatDau > ?)
                    ");
                    $stmtOverlap->execute(array($bdNL, $ktNL));
                    $trung = $stmtOverlap->fetch();
                    if ($trung) {
                        $fieldErrors['ngayLe'] = "Khoảng thời gian bị trùng với ngày lễ đã lưu: '" . $trung['tenQuyTac'] . "' (" . $trung['ngayBatDau'] . " đến " . $trung['ngayKetThuc'] . ").";
                    }
                }
            }
        }

        $ngayKhongDon = trim((string)$formTonKho['soNgayKhongDon']);
        $ngayTinh     = trim((string)$formTonKho['soNgayTinh']);
        $tyLeTon      = trim((string)$formTonKho['tyLeTonKhoConLai']);
        $mucGiam      = trim((string)$formTonKho['mucGiamGia']);

        if ($ngayKhongDon === '' || $ngayTinh === '' || $tyLeTon === '' || $mucGiam === '') {
            $fieldErrors['tonKho'] = "Vui lòng nhập đầy đủ tất cả các trường của quy tắc tồn kho.";
        } else {
            if (!ctype_digit($ngayKhongDon) || (int)$ngayKhongDon <= 0) {
                $fieldErrors['soNgayKhongDon'] = "Số ngày liên tục không có đơn hàng phải là số nguyên dương.";
            }
            if (!ctype_digit($ngayTinh) || (int)$ngayTinh <= 0) {
                $fieldErrors['soNgayTinh'] = "Số ngày xét tồn kho phải là số nguyên dương.";
            }
            if (!is_numeric($tyLeTon) || (float)$tyLeTon <= 0 || (float)$tyLeTon > 100) {
                $fieldErrors['tyLeTonKhoConLai'] = "Tỷ lệ tồn kho còn lại phải lớn hơn 0% và tối đa 100%.";
            }
            if (!is_numeric($mucGiam) || (float)$mucGiam <= 0 || (float)$mucGiam > 10) {
                $fieldErrors['mucGiamGia'] = "Phần trăm giảm giá phải lớn hơn 0% và tối đa 10%.";
            }
        }

        if (empty($fieldErrors)) {
            try {
                if (isset($_POST['simulate_save_error_71'])) {
                    throw new Exception("Simulated DB Error 7.1");
                }
                $pdo->beginTransaction();

                if ($coNhapNgayLe) {
                    $stmtIns = $pdo->prepare("INSERT INTO QuyTacGiaDong (tenQuyTac, loaiQuyTac, mucDieuChinh, ngayBatDau, ngayKetThuc) VALUES (?, 'NgayLe', ?, ?, ?)");
                    $stmtIns->execute(array($tenNL, (float)$tangNL, $bdNL, $ktNL));
                }

                if (isset($quyTacTonKho['maQuyTac'])) {
                    $stmtUpd = $pdo->prepare("UPDATE QuyTacGiaDong SET soNgayKhongDon = ?, soNgayTinh = ?, tyLeTonKhoConLai = ?, mucDieuChinh = ? WHERE maQuyTac = ?");
                    $stmtUpd->execute(array((int)$ngayKhongDon, (int)$ngayTinh, (float)$tyLeTon, (float)$mucGiam, $quyTacTonKho['maQuyTac']));
                } else {
                    $stmtInsTK = $pdo->prepare("INSERT INTO QuyTacGiaDong (tenQuyTac, loaiQuyTac, mucDieuChinh, soNgayKhongDon, soNgayTinh, tyLeTonKhoConLai) VALUES ('Quy tắc tồn kho toàn sàn', 'TonKho', ?, ?, ?, ?)");
                    $stmtInsTK->execute(array((float)$mucGiam, (int)$ngayKhongDon, (int)$ngayTinh, (float)$tyLeTon));
                }

                $pdo->commit();
                $msgSuccess = "Cấu hình quy tắc giá động thành công.";
                $formNgayLe = array('tenQuyTac' => '', 'ngayBatDau' => '', 'ngayKetThuc' => '', 'mucTangGia' => '');
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $msgError = "Không thể lưu cấu hình. Vui lòng thử lại sau.";
            }
        }
    }
}

$dsNgayLe = $pdo->query("SELECT * FROM QuyTacGiaDong WHERE loaiQuyTac = 'NgayLe' ORDER BY ngayBatDau ASC")->fetchAll();
?>

<div class="mb-3">
    <span class="badge bg-secondary">Đặc tả Use Case #6 - Actor: Quản trị viên sàn</span>
    <h3 class="fw-bold mb-0">Cấu Hình Quy Tắc Giá Động Toàn Sàn</h3>
</div>

<?php if ($msgSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle-fill me-1"></i> <?php echo htmlspecialchars($msgSuccess); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($msgError): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-octagon-fill me-1"></i> <?php echo htmlspecialchars($msgError); ?>
        <button type="button" class="btn btn-sm btn-outline-danger ms-3" data-bs-dismiss="alert">Đóng</button>
    </div>
<?php endif; ?>

<form method="POST">
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">Danh sách Ngày lễ / Mùa cao điểm hiện có</div>
                <div class="card-body">
                    <?php if (empty($dsNgayLe)): ?>
                        <p class="text-muted mb-0">Chưa có ngày lễ nào.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tên ngày lễ</th>
                                        <th>Thời gian</th>
                                        <th class="text-center">% Tăng</th>
                                        <th class="text-end">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dsNgayLe as $nl): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo htmlspecialchars($nl['tenQuyTac']); ?></td>
                                            <td class="small text-muted"><?php echo $nl['ngayBatDau']; ?> <br>→ <?php echo $nl['ngayKetThuc']; ?></td>
                                            <td class="text-center"><span class="badge bg-success">+<?php echo $nl['mucDieuChinh']; ?>%</span></td>
                                            <td class="text-end">
                                                <button type="submit" name="delete_holiday_id" value="<?php echo $nl['maQuyTac']; ?>" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Bạn có chắc muốn xóa ngày lễ này không?');">
                                                    Xóa
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-header bg-white fw-bold text-primary">
                    Phần 1 – Thêm ngày lễ / mùa cao điểm mới (Để trống hoàn toàn nếu chỉ cập nhật quy tắc tồn kho)
                </div>
                <div class="card-body">
                    <?php if (isset($fieldErrors['ngayLe'])): ?>
                        <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($fieldErrors['ngayLe']); ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tên ngày lễ (Tối đa 100 ký tự)</label>
                            <input type="text" name="tenQuyTac" class="form-control <?php echo isset($fieldErrors['tenQuyTac'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars($formNgayLe['tenQuyTac']); ?>" placeholder="VD: Tết Nguyên Đán, Sale 12.12">
                            <?php if (isset($fieldErrors['tenQuyTac'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['tenQuyTac']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">% Tăng giá (0 &lt; % ≤ 10%)</label>
                            <input type="text" name="mucTangGia" class="form-control <?php echo isset($fieldErrors['mucTangGia'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars($formNgayLe['mucTangGia']); ?>" placeholder="VD: 7.5">
                            <?php if (isset($fieldErrors['mucTangGia'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['mucTangGia']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ngày bắt đầu (≥ Hôm nay)</label>
                            <input type="date" name="ngayBatDau" class="form-control <?php echo isset($fieldErrors['ngayBatDau'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars($formNgayLe['ngayBatDau']); ?>">
                            <?php if (isset($fieldErrors['ngayBatDau'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['ngayBatDau']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Ngày kết thúc (≥ Ngày bắt đầu)</label>
                            <input type="date" name="ngayKetThuc" class="form-control <?php echo isset($fieldErrors['ngayKetThuc'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars($formNgayLe['ngayKetThuc']); ?>">
                            <?php if (isset($fieldErrors['ngayKetThuc'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['ngayKetThuc']; ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-bold text-success">
                    Phần 2 – Quy tắc giảm giá giải phóng hàng tồn kho bán chậm
                </div>
                <div class="card-body">
                    <?php if (isset($fieldErrors['tonKho'])): ?>
                        <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($fieldErrors['tonKho']); ?></div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Số ngày liên tục không có đơn hàng <span class="text-danger">*</span></label>
                            <input type="text" name="soNgayKhongDon" class="form-control <?php echo isset($fieldErrors['soNgayKhongDon'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars((string)$formTonKho['soNgayKhongDon']); ?>">
                            <?php if (isset($fieldErrors['soNgayKhongDon'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['soNgayKhongDon']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Số ngày xét tồn kho sau nhập <span class="text-danger">*</span></label>
                            <input type="text" name="soNgayTinh" class="form-control <?php echo isset($fieldErrors['soNgayTinh'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars((string)$formTonKho['soNgayTinh']); ?>">
                            <?php if (isset($fieldErrors['soNgayTinh'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['soNgayTinh']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Tỷ lệ tồn kho còn lại (%) (0 &lt; % ≤ 100%) <span class="text-danger">*</span></label>
                            <input type="text" name="tyLeTonKhoConLai" class="form-control <?php echo isset($fieldErrors['tyLeTonKhoConLai'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars((string)$formTonKho['tyLeTonKhoConLai']); ?>">
                            <?php if (isset($fieldErrors['tyLeTonKhoConLai'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['tyLeTonKhoConLai']; ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">% Giảm giá đề xuất (0 &lt; % ≤ 10%) <span class="text-danger">*</span></label>
                            <input type="text" name="mucGiamGia" class="form-control <?php echo isset($fieldErrors['mucGiamGia'])?'is-invalid':''; ?>" 
                                   value="<?php echo htmlspecialchars((string)$formTonKho['mucGiamGia']); ?>">
                            <?php if (isset($fieldErrors['mucGiamGia'])): ?><div class="invalid-feedback"><?php echo $fieldErrors['mucGiamGia']; ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="simulate_save_error_71" id="sim71">
                        <label class="form-check-label small text-muted" for="sim71">
                            [Test Luồng ngoại lệ 7.1] Giả lập lỗi khi lưu cấu hình vào CSDL (Giữ nguyên dữ liệu đang nhập)
                        </label>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <button type="submit" name="btn_save_config" class="btn btn-primary px-4">
                        <i class="bi bi-floppy"></i> Bước 5: Lưu cấu hình
                    </button>
                    <button type="submit" name="btn_cancel" class="btn btn-outline-secondary">
                        Hủy (Luồng 3.1)
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>