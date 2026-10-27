<?php
// File: uc4_xu_ly_vi_pham.php
require_once 'includes/header.php';

$msgSuccess = '';
$msgError = '';

if (isset($_POST['reset_uc4_data'])) {
    $pdo->exec("UPDATE DoanhNghiep SET soLanViPham = 0, trangThaiHoSo = 'DaPheDuyet', trangThaiGianHang = 'DangHoatDong' WHERE id = 'DN01'");
    $pdo->exec("UPDATE DoanhNghiep SET soLanViPham = 1, trangThaiHoSo = 'DaPheDuyet', trangThaiGianHang = 'DangHoatDong' WHERE id = 'DN02'");
    $pdo->exec("UPDATE DoanhNghiep SET soLanViPham = 2, trangThaiHoSo = 'DaPheDuyet', trangThaiGianHang = 'DangHoatDong' WHERE id = 'DN03'");
    $pdo->exec("UPDATE DoanhNghiep SET soLanViPham = 1, trangThaiHoSo = 'DaPheDuyet', trangThaiGianHang = 'DangHoatDong' WHERE id = 'DN04'");
    $pdo->exec("UPDATE SanPham SET trangThaiHienThi = 'HienThi'");
    $pdo->exec("UPDATE HoSoViPham SET trangThaiHoSoViPham = 'ChoXuLy', hinhThucXuLy = NULL, lyDoViPham = NULL WHERE maViPham IN ('VP01','VP02','VP03')");
    $pdo->exec("UPDATE HoSoViPham SET trangThaiHoSoViPham = 'DangKhacPhuc', hinhThucXuLy = 'CanhCao' WHERE maViPham = 'VP04'");
    $msgSuccess = "Đã khôi phục toàn bộ 4 hồ sơ vi phạm mẫu (Lần 1, Lần 2, Lần 3+, Đang khắc phục) để bạn test lại!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uc4_action'])) {
    $maViPham = $_POST['maViPham'];
    $action   = $_POST['uc4_action'];
    $lyDo     = trim(isset($_POST['lyDoQuyetDinh']) ? $_POST['lyDoQuyetDinh'] : '');

    if ($action !== 'confirm_remedy' && $lyDo === '') {
        $msgError = "Vui lòng nhập lý do/ghi chú quyết định trước khi xác nhận.";
    } else {
        try {
            if (isset($_POST['simulate_db_error_91'])) {
                throw new Exception("Simulated DB Failure 9.1");
            }

            $pdo->beginTransaction();

            $stmtVP = $pdo->prepare("
                SELECT vp.*, dn.soLanViPham, dn.tenDoanhNghiep 
                FROM HoSoViPham vp 
                JOIN DoanhNghiep dn ON vp.idDoanhNghiep = dn.id 
                WHERE vp.maViPham = ? FOR UPDATE
            ");
            $stmtVP->execute(array($maViPham));
            $hoSo = $stmtVP->fetch();

            if ($hoSo) {
                $idDN = $hoSo['idDoanhNghiep'];
                $soLanHienTai = (int)$hoSo['soLanViPham'];

                if ($action === 'dismiss') {
                    $stmtUpd = $pdo->prepare("UPDATE HoSoViPham SET trangThaiHoSoViPham = 'KhongViPham', hinhThucXuLy = 'KhongViPham', lyDoViPham = ?, ngayXuLy = NOW() WHERE maViPham = ?");
                    $stmtUpd->execute(array($lyDo, $maViPham));
                    $msgSuccess = "Đã bác bỏ hồ sơ báo cáo vi phạm ($maViPham). Số lần vi phạm và trạng thái hoạt động của doanh nghiệp được giữ nguyên.";
                } elseif ($action === 'confirm_remedy') {
                    $stmtUpd = $pdo->prepare("UPDATE HoSoViPham SET trangThaiHoSoViPham = 'DaKhacPhuc', ngayXuLy = NOW() WHERE maViPham = ?");
                    $stmtUpd->execute(array($maViPham));
                    addNotification($pdo, $idDN, "Xác nhận hoàn tất khắc phục vi phạm", "Quản trị viên đã xác nhận báo cáo khắc phục cho hồ sơ $maViPham. Dừng bộ đếm hạn 3 ngày.");
                    $msgSuccess = "Đã xác nhận doanh nghiệp hoàn tất khắc phục sai phạm ($maViPham). Dừng bộ đếm hạn 3 ngày, giữ nguyên soLanViPham = 1.";
                } elseif ($action === 'apply_penalty') {
                    $lanMoi = $soLanHienTai + 1;

                    if ($soLanHienTai == 0) {
                        $stmtUpd = $pdo->prepare("UPDATE HoSoViPham SET lanViPham = 1, hinhThucXuLy = 'CanhCao', thoiHanKhacPhuc = 3, lyDoViPham = ?, trangThaiHoSoViPham = 'DangKhacPhuc', ngayXuLy = NOW() WHERE maViPham = ?");
                        $stmtUpd->execute(array($lyDo, $maViPham));
                        $stmtDN = $pdo->prepare("UPDATE DoanhNghiep SET soLanViPham = 1 WHERE id = ?");
                        $stmtDN->execute(array($idDN));
                        addNotification($pdo, $idDN, "Cảnh cáo vi phạm lần 1", "Yêu cầu khắc phục trong 3 ngày: $lyDo");
                        $msgSuccess = "Đã áp dụng biện pháp cảnh cáo vi phạm lần 1 thành công (Thời hạn khắc phục: 3 ngày).";
                    } elseif ($soLanHienTai == 1) {
                        $stmtUpd = $pdo->prepare("UPDATE HoSoViPham SET lanViPham = 2, hinhThucXuLy = 'TamKhoa', lyDoViPham = ?, trangThaiHoSoViPham = 'DaXuLy', ngayXuLy = NOW() WHERE maViPham = ?");
                        $stmtUpd->execute(array($lyDo, $maViPham));
                        $stmtDN = $pdo->prepare("UPDATE DoanhNghiep SET soLanViPham = 2, trangThaiGianHang = 'TamKhoa' WHERE id = ?");
                        $stmtDN->execute(array($idDN));
                        $stmtSP = $pdo->prepare("UPDATE SanPham SET trangThaiHienThi = 'An', trangThaiDynamicPricing = 0 WHERE idDoanhNghiep = ?");
                        $stmtSP->execute(array($idDN));
                        addNotification($pdo, $idDN, "Quyết định Tạm khóa gian hàng 7 ngày (Vi phạm lần 2)", "Lý do: $lyDo. Toàn bộ sản phẩm đã bị ẩn và tạm ngưng Dynamic Pricing.");
                        $msgSuccess = "Đã áp dụng chế tài tạm khóa gian hàng 7 ngày thành công (Vi phạm lần 2: Đã ẩn toàn bộ sản phẩm và tạm ngưng Dynamic Pricing).";
                    } else {
                        $stmtUpd = $pdo->prepare("UPDATE HoSoViPham SET lanViPham = ?, hinhThucXuLy = 'Khoa', lyDoViPham = ?, trangThaiHoSoViPham = 'DaXuLy', ngayXuLy = NOW() WHERE maViPham = ?");
                        $stmtUpd->execute(array($lanMoi, $lyDo, $maViPham));
                        $stmtDN = $pdo->prepare("UPDATE DoanhNghiep SET soLanViPham = ?, trangThaiHoSo = 'BiKhoa', trangThaiGianHang = 'Dong' WHERE id = ?");
                        $stmtDN->execute(array($lanMoi, $idDN));
                        $stmtSP = $pdo->prepare("UPDATE SanPham SET trangThaiHienThi = 'An', trangThaiDynamicPricing = 0 WHERE idDoanhNghiep = ?");
                        $stmtSP->execute(array($idDN));
                        addNotification($pdo, $idDN, "Quyết định Khóa vĩnh viễn tài khoản (Vi phạm lần $lanMoi)", "Lý do: $lyDo. Gian hàng đã đóng và ẩn vĩnh viễn toàn bộ sản phẩm.");
                        $msgSuccess = "Đã khóa vĩnh viễn tài khoản doanh nghiệp thành công (Vi phạm lần $lanMoi: Đóng gian hàng và ẩn vĩnh viễn toàn bộ sản phẩm).";
                    }

                    if (isset($_POST['simulate_email_queue_101'])) {
                        $msgSuccess = "Đã áp dụng chế tài thành công. Thông báo gửi đến doanh nghiệp đang được hệ thống xử lý lại (Retry Queue).";
                    }
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $msgError = "Không thể ghi nhận quyết định xử lý vi phạm do sự cố hệ thống. Vui lòng thử lại sau.";
        }
    }
}

$dsHoSo = $pdo->query("
    SELECT vp.*, dn.tenDoanhNghiep, dn.maSoThue, dn.soLanViPham, dn.trangThaiGianHang, dn.trangThaiHoSo
    FROM HoSoViPham vp
    JOIN DoanhNghiep dn ON vp.idDoanhNghiep = dn.id
    WHERE vp.trangThaiHoSoViPham IN ('ChoXuLy', 'DangKhacPhuc')
    ORDER BY vp.maViPham ASC
")->fetchAll();

$defaultMaVP = isset($dsHoSo[0]['maViPham']) ? $dsHoSo[0]['maViPham'] : '';
$maVPChon = isset($_GET['maViPham']) ? $_GET['maViPham'] : $defaultMaVP;
$hoSoChon = null;
foreach ($dsHoSo as $hs) {
    if ($hs['maViPham'] === $maVPChon) {
        $hoSoChon = $hs;
        break;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="badge bg-warning text-dark">Đặc tả Use Case #4 - Actor: Quản trị viên sàn</span>
        <h3 class="fw-bold mb-0">Xử Lý Vi Phạm Doanh Nghiệp (Chế Tài Lũy Tiến)</h3>
    </div>
    <form method="POST">
        <button type="submit" name="reset_uc4_data" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise"></i> Khôi phục 4 hồ sơ mẫu để test lại
        </button>
    </form>
</div>

<?php if ($msgSuccess): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle-fill me-1"></i> <?php echo $msgSuccess; ?></div>
<?php endif; ?>
<?php if ($msgError): ?>
    <div class="alert alert-danger"><i class="bi bi-x-octagon-fill me-1"></i> <?php echo $msgError; ?></div>
<?php endif; ?>

<?php if (empty($dsHoSo)): ?>
    <div class="alert alert-info">Hiện chưa có hồ sơ vi phạm nào cần xử lý.</div>
<?php else: ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header bg-white fw-bold">Danh sách hồ sơ báo cáo vi phạm</div>
            <div class="list-group list-group-flush">
                <?php foreach ($dsHoSo as $item): ?>
                    <a href="?maViPham=<?php echo urlencode($item['maViPham']); ?>" 
                       class="list-group-item list-group-item-action <?php echo ($hoSoChon && $hoSoChon['maViPham']===$item['maViPham']) ? 'active' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong><?php echo htmlspecialchars($item['maViPham']); ?> - <?php echo htmlspecialchars($item['loaiViPham']); ?></strong>
                            <span class="badge <?php echo ($item['trangThaiHoSoViPham']==='ChoXuLy') ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                                <?php echo htmlspecialchars($item['trangThaiHoSoViPham']); ?>
                            </span>
                        </div>
                        <div class="small mt-1"><?php echo htmlspecialchars($item['tenDoanhNghiep']); ?></div>
                        <div class="small mt-1 opacity-75">
                            Vi phạm lũy kế hiện tại: <strong><?php echo $item['soLanViPham']; ?> lần</strong> | Ngày báo cáo: <?php echo $item['ngayBaoCao']; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($hoSoChon): 
            $soLan = (int)$hoSoChon['soLanViPham'];
            if ($soLan == 0) {
                $mucDeXuat = "Vi phạm lần 1: Cảnh cáo và yêu cầu khắc phục trong vòng 3 ngày";
                $badgeDeXuat = "bg-warning text-dark";
            } elseif ($soLan == 1) {
                $mucDeXuat = "Vi phạm lần 2: Tạm khóa gian hàng 7 ngày, ẩn toàn bộ sản phẩm và tạm ngưng Dynamic Pricing";
                $badgeDeXuat = "bg-danger";
            } else {
                $mucDeXuat = "Vi phạm lần " . ($soLan + 1) . " (Lần 3+): Khóa vĩnh viễn tài khoản doanh nghiệp, đóng gian hàng và ẩn vĩnh viễn toàn bộ sản phẩm";
                $badgeDeXuat = "bg-dark";
            }
        ?>
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Chi tiết Hồ sơ Vi phạm: <?php echo htmlspecialchars($hoSoChon['maViPham']); ?></span>
                <span class="badge bg-secondary">Nguồn: <?php echo htmlspecialchars($hoSoChon['nguonBaoCao']); ?></span>
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded border">
                    <div><strong>Doanh nghiệp:</strong> <?php echo htmlspecialchars($hoSoChon['tenDoanhNghiep']); ?> (MST: <?php echo htmlspecialchars($hoSoChon['maSoThue']); ?>)</div>
                    <div><strong>Số lần vi phạm lũy kế (<code>soLanViPham</code>):</strong> <span class="badge bg-danger"><?php echo $soLan; ?> lần</span></div>
                    <div class="mt-2"><strong>Mô tả hành vi sai phạm:</strong> <?php echo htmlspecialchars($hoSoChon['moTaSaiPham']); ?></div>
                    <div class="mt-1 text-primary small"><strong><i class="bi bi-file-earmark-code"></i> Dữ liệu / Log chứng cứ:</strong> <?php echo htmlspecialchars($hoSoChon['chungCuLog']); ?></div>
                </div>

                <?php if ($hoSoChon['trangThaiHoSoViPham'] === 'DangKhacPhuc'): ?>
                    <div class="alert alert-warning">
                        <h6 class="fw-bold"><i class="bi bi-tools"></i> Báo cáo khắc phục từ Doanh nghiệp (Luồng thay thế 5.4):</h6>
                        <p class="mb-2"><?php echo htmlspecialchars($hoSoChon['baoCaoKhacPhuc']); ?></p>
                        <form method="POST">
                            <input type="hidden" name="maViPham" value="<?php echo htmlspecialchars($hoSoChon['maViPham']); ?>">
                            <button type="submit" name="uc4_action" value="confirm_remedy" class="btn btn-success">
                                <i class="bi bi-check2-all"></i> Bước 5.4.3: Xác nhận đã khắc phục (Dừng bộ đếm 3 ngày)
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary border-start border-4 border-danger">
                        <div class="small text-muted">Bước 5 - Hệ thống đối chiếu lịch sử và đề xuất mức chế tài:</div>
                        <span class="badge <?php echo $badgeDeXuat; ?> fs-6 mt-1 text-wrap text-start"><?php echo $mucDeXuat; ?></span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="maViPham" value="<?php echo htmlspecialchars($hoSoChon['maViPham']); ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Bước 6: Nhập lý do xử phạt / Yêu cầu khắc phục / Ghi chú bác bỏ <span class="text-danger">*</span></label>
                            <textarea name="lyDoQuyetDinh" class="form-control" rows="3" placeholder="Nhập lý do chi tiết gửi đến doanh nghiệp..."><?php echo htmlspecialchars(isset($_POST['lyDoQuyetDinh']) ? $_POST['lyDoQuyetDinh'] : ''); ?></textarea>
                        </div>

                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="simulate_db_error_91" id="sim91">
                            <label class="form-check-label small text-muted" for="sim91">
                                [Test Luồng ngoại lệ 9.1] Giả lập lỗi kết nối CSDL để kiểm tra Rollback
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="simulate_email_queue_101" id="sim101">
                            <label class="form-check-label small text-muted" for="sim101">
                                [Test Luồng ngoại lệ 10.1] Giả lập gián đoạn dịch vụ gửi thông báo (Đưa vào hàng đợi Retry Queue)
                            </label>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="uc4_action" value="apply_penalty" class="btn btn-danger"
                                    onclick="return confirm('Hộp thoại xác nhận: Bạn có chắc chắn đồng ý áp dụng chế tài đối với doanh nghiệp này không?');">
                                <i class="bi bi-hammer"></i> Xác nhận xử phạt
                            </button>
                            <button type="submit" name="uc4_action" value="dismiss" class="btn btn-outline-success">
                                <i class="bi bi-shield-check"></i> Bác bỏ hồ sơ - Không vi phạm (Luồng 5.3)
                            </button>
                            <a href="uc4_xu_ly_vi_pham.php" class="btn btn-outline-secondary">Hủy bỏ (Luồng 6.1)</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>