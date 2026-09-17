# FIFO Rules

## 1. Purpose

FIFO được sử dụng để xác định StockLot nào được sử dụng trước khi xuất kho.

FIFO:

> First In, First Out

---

## 2. Scope

FIFO áp dụng cho inventory issue.

FIFO hiện tại dựa trên thời điểm receipt:

```text
received_at ASC
```

Không sử dụng expiry date để quyết định thứ tự trong implementation hiện tại.

---

## 3. Eligible Lots

Chỉ các StockLot thỏa mãn tất cả điều kiện sau mới được chọn:

```text
warehouse_id = requested warehouse
product_id = requested product
quantity_remaining > 0
```

---

## 4. Ordering

Thứ tự FIFO:

```text
ORDER BY received_at ASC, id ASC
```

`id ASC` là tie-breaker để đảm bảo thứ tự deterministic khi hai lot có cùng `received_at`.

---

## 5. Example

Receipt:

```text
01/06 → 20
15/06 → 30
01/07 → 50
```

Current:

```text
Lot 1 = 20
Lot 2 = 30
Lot 3 = 50

Total = 100
```

Issue:

```text
25
```

Result:

```text
Lot 1:
20 → 0

Lot 2:
30 → 25

Lot 3:
50 → 50
```

Stock:

```text
100 → 75
```

Allocation:

```text
Lot 1 → 20
Lot 2 → 5
```

---

## 6. Single Lot Issue

Nếu:

```text
Lot 1 = 50
Issue = 20
```

thì:

```text
Lot 1 = 30
```

Allocation:

```text
Lot 1 → 20
```

---

## 7. Multi Lot Issue

Nếu:

```text
Lot 1 = 20
Lot 2 = 30
Lot 3 = 50

Issue = 55
```

thì:

```text
Lot 1 → 0
Lot 2 → 0
Lot 3 → 45
```

Allocation:

```text
Lot 1 → 20
Lot 2 → 30
Lot 3 → 5
```

---

## 8. Insufficient Stock

Nếu:

```text
Total available = 40
Request = 50
```

operation phải fail.

Không được:

```text
issue 40
then partially fail
```

trừ khi business requirement sau này explicitly cho phép partial issue.

Mặc định project hiện tại:

```text
Issue is atomic.
```

---

## 9. Empty Lot

StockLot có:

```text
quantity_remaining = 0
```

không được chọn cho issue.

Lot vẫn có thể được giữ lại để phục vụ history/traceability.

---

## 10. Expiry

`expiry_date` có thể tồn tại nhưng FIFO hiện tại không dựa trên expiry.

Nếu sau này cần FEFO:

```text
First Expired, First Out
```

thì phải tạo/thay đổi business rule riêng.

Không tự động chuyển FIFO thành FEFO chỉ vì StockLot có `expiry_date`.

---

## 11. Concurrency

FIFO selection và quantity update phải được thực hiện trong cùng transaction với locking phù hợp.

Không được:

```text
SELECT lots
    ↓
release lock
    ↓
update later
```

nếu điều đó cho phép transaction khác tiêu thụ cùng quantity.

Mục tiêu:

```text
FIFO selection
+
quantity update
+
allocation
+
movement
```

phải được xử lý atomically.

---

## 12. Determinism

Cùng một trạng thái database và cùng một request phải tạo ra cùng thứ tự FIFO.

Ordering bắt buộc:

```text
received_at ASC
id ASC
```
