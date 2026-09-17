# Product Module

## 1. Mục đích

Product Module quản lý master data của sản phẩm.

Module này không chịu trách nhiệm quản lý số lượng tồn kho.

---

## 2. Product

Product đại diện cho một loại sản phẩm được quản lý trong hệ thống.

Thông tin có thể bao gồm:

* ID
* SKU
* Tên sản phẩm
* Mô tả
* Category
* Đơn vị tính
* Giá
* Trạng thái
* Các thuộc tính master data khác

Các field thực tế phải tuân theo source code/database hiện hành.

---

## 3. Product không chứa quantity

Không thêm inventory quantity vào Product.

Không sử dụng:

```text
Product.quantity
Product.stock
Product.quantity_on_hand
Product.inventory_quantity
```

để biểu diễn tồn kho.

Tồn kho thuộc về:

```text
Stock
```

và được xác định theo:

```text
Product + Warehouse
```

---

## 4. Quan hệ

```text
Product
   │
   ├──< Stock
   │
   └──< StockLot
```

Product có thể tồn tại ở nhiều warehouse.

---

## 5. Inventory separation

Product Module trả lời:

> Sản phẩm này là gì?

Inventory Module trả lời:

> Sản phẩm này hiện còn bao nhiêu trong từng kho?

Hai trách nhiệm này phải được giữ độc lập.

---

## 6. Validation

Product validation phải tập trung vào master data.

Ví dụ:

* SKU hợp lệ.
* Tên sản phẩm hợp lệ.
* Category hợp lệ.
* Đơn vị tính hợp lệ.
* Trạng thái hợp lệ.

Không đưa logic FIFO hoặc inventory mutation vào Product Module.

---

## 7. Future extension

Trong tương lai Product có thể mở rộng để hỗ trợ:

* expiry-related configuration;
* serial tracking;
* barcode;
* product variants;
* additional attributes.

Việc mở rộng Product không được phá vỡ inventory architecture hiện tại.
