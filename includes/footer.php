<?php
// File: includes/footer.php
$notifs = $pdo->query("SELECT * FROM ThongBao ORDER BY id DESC LIMIT 5")->fetchAll();
?>
</div><!-- Kết thúc .container -->

<footer class="bg-white border-top mt-5 py-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="fw-bold text-primary"><i class="bi bi-cpu"></i> Hệ thống TMĐT B2C Tích hợp Quản lý Giá Động (Dynamic Pricing)</h6>
                <p class="text-muted small mb-1">Đồ án môn học Phân tích & Thiết kế Hệ thống Thông tin - Nhóm SSS Hữu Hạn (DHHTTT19B).</p>
                <p class="text-muted small mb-0">Thành viên: Lê Ngọc An, Võ Nguyễn Nhật Huy, Nguyễn Đức Hậu, Phùng Quốc Khoa, Nguyễn Thái Phong, Võ Quốc Trung.</p>
            </div>
            <div class="col-md-6">
                <h6 class="fw-bold text-dark"><i class="bi bi-bell"></i> Nhật ký Thông báo Hệ thống & Cổng thanh toán (5 sự kiện mới nhất)</h6>
                <?php if (empty($notifs)): ?>
                    <p class="text-muted small mb-0">Chưa có thông báo mới.</p>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($notifs as $n): ?>
                            <li class="border-bottom py-1">
                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($n['nguoiNhan']); ?></span>
                                <strong><?php echo htmlspecialchars($n['tieuDe']); ?>:</strong>
                                <span class="text-muted"><?php echo htmlspecialchars($n['noiDung']); ?></span>
                                <em class="text-secondary" style="font-size:0.75rem;">(<?php echo $n['thoiGian']; ?>)</em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>