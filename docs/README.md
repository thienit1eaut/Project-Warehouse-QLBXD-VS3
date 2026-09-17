# DATN2026-VS2 - Project Documentation

## 1. Mục đích

Thư mục `docs/` chứa tài liệu chính thức mô tả kiến trúc, nghiệp vụ, cơ sở dữ liệu và quy tắc phát triển của project.

Các tài liệu trong thư mục này được sử dụng làm nguồn tham chiếu chung cho:

* Developer
* Claude
* Codex
* Cursor
* Các công cụ AI khác
* Việc review và bảo trì project trong tương lai

Source code là nơi chứa implementation thực tế.

`docs/` là nơi mô tả **ý định thiết kế, kiến trúc và quy tắc nghiệp vụ**.

Hai phần này phải nhất quán với nhau.

---

## 2. Cấu trúc tài liệu

### `architecture/`

Mô tả kiến trúc và trách nhiệm của các module.

* `system-architecture.md`: kiến trúc tổng thể.
* `product-module.md`: module Product.
* `inventory-module.md`: module Inventory.

### `database/`

Mô tả thiết kế database và các invariant quan trọng.

* `inventory-database.md`: các bảng và quan hệ liên quan đến tồn kho.

### `business/`

Mô tả các quy tắc nghiệp vụ.

* `inventory-rules.md`: quy tắc tồn kho.
* `fifo-rules.md`: quy tắc FIFO.
* `stock-allocation.md`: quy tắc phân bổ hàng xuất vào StockLot.

### `ai/`

Mô tả context và quy tắc để AI làm việc với project.

* `project-context.md`: context tổng quát.
* `coding-rules.md`: coding/development guardrails.
* `current-phase.md`: phase hiện tại và phạm vi được phép thay đổi.
* `change-log.md`: lịch sử thay đổi quan trọng.

---

## 3. Source of Truth

Khi có sự khác biệt giữa các nguồn, ưu tiên kiểm tra theo thứ tự:

1. Quyết định nghiệp vụ/kiến trúc đã được chủ project xác nhận.
2. Tài liệu architecture/business hiện hành.
3. Database schema và migrations.
4. Source code.
5. AI-generated assumptions.

AI không được tự suy luận rằng implementation hiện tại luôn đúng nếu implementation mâu thuẫn với architecture contract.

Nếu phát hiện conflict:

1. Dừng thay đổi liên quan.
2. Báo rõ conflict.
3. Chỉ ra các file/logic bị ảnh hưởng.
4. Đề xuất phương án.
5. Chờ quyết định trước khi redesign.

---

## 4. Nguyên tắc cập nhật tài liệu

Khi một thay đổi làm thay đổi:

* Entity
* Quan hệ database
* Business rule
* Inventory invariant
* FIFO behavior
* Transaction boundary
* Architecture boundary

thì phải cập nhật tài liệu liên quan.

Không được chỉ sửa code mà bỏ qua architecture/business documentation.

---

## 5. Quy tắc sử dụng với AI

Trước khi thực hiện một task lớn, AI phải đọc tối thiểu:

```text
docs/ai/project-context.md
docs/ai/coding-rules.md
docs/ai/current-phase.md
```

Sau đó đọc thêm tài liệu liên quan trực tiếp đến task.

AI không được mở rộng phạm vi implementation ngoài `current-phase.md` nếu chưa được cho phép.
