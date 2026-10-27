# Hệ Thống Thông Tin Quản Lý Giá Động (Dynamic Pricing) Trên Sàn TMĐT B2C

Đồ án môn học Phân tích & Thiết kế Hệ thống Thông tin - **Nhóm SSS Hữu Hạn (Lớp DHHTTT19B)**.

## 1. Thành viên nhóm thực hiện
* **Lê Ngọc An** - Trưởng nhóm
* **Võ Nguyễn Nhật Huy** - Thư ký
* **Nguyễn Đức Hậu** - Người lưu giữ hồ sơ
* **Phùng Quốc Khoa** - Time Keeper
* **Nguyễn Thái Phong** - Thành viên
* **Võ Quốc Trung** - Thành viên

## 2. Trọng tâm 6 Đặc tả Use Case trong hệ thống
1. **Thiết lập giá sản phẩm (`uc1_thiet_lap_gia.php`)**: Thiết lập Giá sàn, Giá cơ sở, Giá trần (`0 < Giá sàn ≤ Giá cơ sở ≤ Giá trần`), kiểm tra điều kiện gói dịch vụ và bật/tắt Dynamic Pricing.
2. **Thanh toán gói dịch vụ (`uc2_thanh_toan_goi.php`)**: Thanh toán qua Cổng thanh toán trực tuyến (VNPay, MoMo, Thẻ quốc tế), xác thực OTP, kích hoạt gian hàng và đối soát tự động khi gián đoạn đường truyền.
3. **Hủy đơn hàng - Khách hàng (`uc3_huy_don_hang_kh.php`)**: Hủy đơn ở trạng thái `Chờ xử lý`, chọn lý do hủy, hoàn trả tồn kho, giảm số lượng đã bán và hoàn tiền qua Cổng thanh toán.
4. **Xử lý vi phạm doanh nghiệp (`uc4_xu_ly_vi_pham.php`)**: Áp dụng chế tài lũy tiến theo `soLanViPham` (Lần 1: Cảnh cáo 3 ngày -> Lần 2: Tạm khóa 7 ngày & ẩn SP -> Lần 3+: Khóa vĩnh viễn), Bác bỏ hồ sơ hoặc Xác nhận đã khắc phục.
5. **Thanh toán đơn hàng (`uc5_thanh_toan_don_hang.php`)**: Tích hợp cơ chế Khóa giá (`Price Lock 15 phút`), tạm giữ tồn kho, cho phép thử lại tối đa 3 lần, tự động giải phóng kho hoặc hoàn tiền nếu quá hạn Price Lock.
6. **Cấu hình quy tắc giá động (`uc6_cau_hinh_gia_dong.php`)**: Quản trị viên thiết lập lịch tăng giá ngày lễ (kiểm tra trùng ngày, biên độ ≤ 10%) và quy tắc giảm giá giải phóng hàng tồn kho.

## 3. Hướng dẫn cài đặt và chạy dự án (WampServer)
1. Copy thư mục `dynamic-pricing-b2c` vào `C:\wamp\www\`.
2. Khởi động **WampServer**.
3. Mở trình duyệt truy cập: `http://localhost:8080/dynamic-pricing-b2c/` (Hệ thống sẽ tự động khởi tạo CSDL `dynamic_pricing_b2c` và nạp sẵn dữ liệu mẫu).