# Stock Allocation Rules

## 1. Purpose

StockAllocation ghi nhận lượng hàng của một OUT movement được lấy từ StockLot nào.

Nó phục vụ traceability.

---

## 2. Relationship

```text
StockMovement
    │
    │ type = OUT
    │
    ├── StockAllocation → StockLot
    ├── StockAllocation → StockLot
    └── ...
```

Một OUT movement có thể có một hoặc nhiều allocation.

---

## 3. Quantity

Mỗi allocation phải có:

```text
quantity > 0
```

Tổng allocation của một OUT movement phải bằng quantity của movement.

Invariant:

```text
SUM(StockAllocation.quantity)
=
StockMovement.OUT.quantity
```

---

## 4. FIFO Example

Stock:

```text
Lot A = 20
Lot B = 30
Lot C = 50
```

Issue:

```text
25
```

Allocations:

```text
A = 20
B = 5
```

Không tạo:

```text
A = 25
```

vì A chỉ còn 20.

---

## 5. Partial Lot Consumption

Nếu:

```text
Lot A = 50
Issue = 20
```

thì:

```text
Lot A remaining = 30
Allocation = 20
```

Allocation không phải là số lượng còn lại.

Allocation là:

> Số lượng của Lot đã được sử dụng bởi movement đó.

---

## 6. Multiple Lots

Một OUT movement có thể:

```text
OUT = 75

Allocation:
Lot A = 20
Lot B = 30
Lot C = 25
```

Tổng:

```text
20 + 30 + 25 = 75
```

---

## 7. Transaction

StockAllocation phải được tạo trong cùng transaction với:

* Stock update
* StockLot update
* StockMovement creation

Nếu allocation fail:

```text
ROLLBACK
```

---

## 8. No Manual Allocation From UI

UI không được tự quyết định allocation.

UI chỉ gửi:

```text
product
warehouse
quantity
```

InventoryService quyết định:

```text
which lots
how much from each lot
```

theo FIFO rule.

---

## 9. Audit

Allocation không thay thế StockMovement.

Hai entity có trách nhiệm khác nhau:

```text
StockMovement
→ What inventory movement happened?

StockAllocation
→ Which lots were consumed by this OUT movement?
```

---

## 10. Future Traceability

StockAllocation cho phép truy ngược:

```text
OUT movement
    ↓
StockAllocation
    ↓
StockLot
    ↓
Receipt origin
```

Thiết kế này tạo nền tảng cho traceability mà không cần triển khai hệ thống batch/serial phức tạp.
