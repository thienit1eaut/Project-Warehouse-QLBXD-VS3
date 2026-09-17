# AI Coding Rules

## 1. Read Before Modify

Trước khi sửa code, AI phải đọc:

```text
docs/ai/project-context.md
docs/ai/current-phase.md
```

và các tài liệu architecture/business liên quan.

Sau đó kiểm tra source code hiện tại.

---

## 2. Preserve Existing Working Code

Không rewrite một module đang hoạt động chỉ vì AI thích một cách implementation khác.

Chỉ refactor khi:

* Task yêu cầu.
* Code gây lỗi.
* Architecture yêu cầu.
* Refactor cần thiết để implement feature.

---

## 3. Respect Current Architecture

Không tự ý thay đổi:

* Entity responsibility.
* Service boundary.
* Database design.
* Naming convention.
* Layer architecture.

Nếu cần thay đổi architecture:

```text
STOP → REPORT CONFLICT → PROPOSE → WAIT
```

---

## 4. Controller

Controller chịu trách nhiệm:

* Nhận request.
* Validate/request mapping.
* Gọi application/service layer.
* Trả response.

Không đặt business logic inventory phức tạp trong Controller.

---

## 5. Service

Service chịu trách nhiệm business logic.

Inventory mutation phải đi qua:

```text
InventoryService
```

---

## 6. Repository / Persistence

Repository hoặc persistence layer chịu trách nhiệm database interaction.

Không để repository tự quyết định business rule.

Ví dụ:

Repository có thể:

```text
findAvailableLots()
save()
update()
lockForUpdate()
```

Nhưng FIFO business rule phải được điều phối bởi service/domain logic phù hợp.

---

## 7. UI

UI không được:

* Tính FIFO.
* Chọn StockLot để xuất.
* Trực tiếp update inventory.
* Tự tính lại authoritative stock quantity.

UI chỉ gửi business request và hiển thị result.

---

## 8. Database Transaction

Các operation:

```text
Receive
Issue
Adjustment
```

phải có transaction boundary rõ ràng.

Không commit một phần inventory operation.

---

## 9. InventoryService Boundary

Không bypass:

```text
InventoryService
```

để cập nhật:

```text
Stock
StockLot
StockMovement
StockAllocation
```

---

## 10. Database Schema

Không thay đổi schema ngoài scope của current phase.

Nếu task yêu cầu schema change nhưng current phase không cho phép:

```text
STOP
REPORT
```

---

## 11. Naming

Tuân thủ naming convention đang tồn tại trong project.

Không tự ý đổi:

```text
table names
column names
class names
method names
API conventions
```

chỉ vì sở thích cá nhân.

---

## 12. Dependencies

Không thêm framework/library/package mới nếu không cần thiết.

Nếu dependency mới thực sự cần:

```text
Explain:
- Why needed
- What problem it solves
- Alternatives
- Impact
```

Sau đó chờ approval nếu thay đổi có ảnh hưởng lớn.

---

## 13. Tests

Mọi implementation có business behavior phải có test phù hợp.

Đặc biệt inventory cần test:

* Receive.
* Issue.
* Insufficient stock.
* FIFO.
* Multiple lots.
* Adjustment.
* Invariants.
* Transaction rollback.
* Concurrency khi scope yêu cầu.

---

## 14. Error Handling

Không silently ignore business error.

Ví dụ:

```text
Insufficient stock
Invalid product
Invalid warehouse
Invalid quantity
Missing Stock
```

phải được xử lý rõ ràng.

---

## 15. Inventory Invariants

Không commit code làm phá vỡ:

```text
Stock.quantity_on_hand >= 0

0 <= StockLot.quantity_remaining
   <= StockLot.quantity_received

Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)

SUM(StockAllocation.quantity)
=
OUT movement quantity
```

---

## 16. AI Must Not Invent Existing Code

Nếu không tìm thấy:

* class
* method
* table
* migration
* repository
* service

thì không được giả định rằng nó tồn tại.

Phải:

1. Search project.
2. Nếu không có, báo rõ.
3. Chỉ tạo mới khi scope cho phép.

---

## 17. After Implementation

Sau khi sửa xong phải báo:

```text
Files created
Files modified
Files deleted (if any)

Key changes

Tests executed

Test result

Potential risks
```

---

## 18. Minimal Change Principle

Ưu tiên:

```text
Smallest correct change
```

thay vì:

```text
Largest possible refactor
```

---

## 19. Phase Discipline

AI chỉ được thay đổi những phần được phép trong:

```text
docs/ai/current-phase.md
```

Không "tiện thể" triển khai phase tiếp theo.

---

## 20. Git Discipline

Một task/feature lớn nên có commit rõ nghĩa.

Ví dụ:

```text
feat(product): complete product validation

feat(inventory): add warehouse and stock

feat(inventory): add stock movement

feat(inventory): create stock lot on receiving

feat(inventory): implement fifo allocation

test(inventory): add fifo issue scenarios

fix(inventory): prevent negative stock
```
