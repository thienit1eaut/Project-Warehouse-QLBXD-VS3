# Inventory Business Rules

## 1. General Rule

Inventory là nguồn dữ liệu quản lý số lượng tồn kho.

Product không trực tiếp lưu quantity.

---

## 2. Stock Rule

Một Product tại một Warehouse có tối đa một Stock record.

```text
UNIQUE(warehouse_id, product_id)
```

Stock biểu diễn current quantity.

---

## 3. StockLot Rule

Mỗi lần nhập hàng tạo một StockLot mới.

Không gộp nhiều receipt thành một StockLot nếu việc gộp làm mất thông tin nguồn gốc receipt.

Ví dụ:

```text
01/06 receive 20
15/06 receive 30
```

Phải tạo:

```text
Lot A = 20
Lot B = 30
```

Không tạo:

```text
Lot A = 50
```

---

## 4. Receive Rule

Khi receive:

```text
Stock.quantity_on_hand += received_quantity

Create StockLot:
    quantity_received = received_quantity
    quantity_remaining = received_quantity

Create StockMovement:
    type = IN
    quantity = received_quantity
```

Các thay đổi phải atomic.

---

## 5. Issue Rule

Không được xuất vượt quá tồn kho.

Nếu:

```text
requested_quantity > available_quantity
```

thì transaction phải fail.

Không được để:

```text
Stock.quantity_on_hand < 0
```

và:

```text
StockLot.quantity_remaining < 0
```

---

## 6. Issue Result

Khi issue thành công:

```text
Stock.quantity_on_hand -= issued_quantity
```

StockLot được giảm theo FIFO.

OUT movement được tạo.

StockAllocation được tạo cho từng Lot bị sử dụng.

---

## 7. Adjustment Rule

Adjustment được sử dụng khi:

```text
Actual quantity != System quantity
```

Không được âm thầm sửa quantity để "đồng bộ".

Phải tạo Adjustment movement.

---

## 8. Positive Adjustment

Nếu thực tế nhiều hơn hệ thống:

```text
delta > 0
```

thì:

```text
Stock.quantity_on_hand += delta
```

và tạo StockLot tương ứng cho lượng adjustment nếu thiết kế hiện tại yêu cầu giữ invariant nguồn gốc.

---

## 9. Negative Adjustment

Nếu thực tế ít hơn hệ thống:

```text
delta < 0
```

phải giảm inventory theo một chiến lược xác định.

Chiến lược mặc định:

```text
FIFO
```

Không được chọn ngẫu nhiên một StockLot để giảm.

---

## 10. Audit Rule

Mọi inventory mutation phải có history tương ứng.

Các mutation chính:

```text
Receive → IN
Issue → OUT
Adjustment → ADJUSTMENT
```

---

## 11. No Direct Mutation

Không được thực hiện:

```text
Controller → stock.quantity_on_hand = ...
UI → stock.quantity_on_hand = ...
```

Mọi mutation phải đi qua InventoryService.

---

## 12. Atomicity

Nếu một bước trong inventory operation thất bại:

```text
ROLLBACK
```

Không được xảy ra trạng thái:

```text
Stock updated
nhưng StockLot chưa update
```

hoặc:

```text
StockLot updated
nhưng Movement chưa tạo
```

---

## 13. Concurrency

Inventory mutation phải bảo vệ against race condition.

Các record cần thiết phải được lock trong transaction tùy theo implementation.

Mục tiêu là đảm bảo:

```text
No negative stock
No lost update
No inconsistent Stock/StockLot state
No incorrect FIFO allocation
```
