# Đồ án quản lý kho — DATN2026-VS3

Hệ thống quản lý kho được xây dựng trong khuôn khổ đồ án tốt nghiệp, tập trung vào quản lý sản phẩm, kho hàng và các nghiệp vụ nhập — xuất — điều chỉnh tồn kho.

## Phạm vi

Hệ thống hiện tập trung vào các sản phẩm liên quan đến xe đạp:

* Xe đạp
* Phụ tùng
* Phụ kiện
* Săm / lốp
* Xích
* Phanh
* Linh kiện

Thiết kế được xây dựng theo hướng có thể mở rộng trong tương lai nhưng không đưa các nghiệp vụ ERP phức tạp vào phạm vi hiện tại.

## Kiến trúc tồn kho

Các thành phần chính:

```text
Product
   │
   └── Stock
         │
         └── StockLot

Warehouse
   │
   └── Stock

InventoryService
   ├── Receive
   ├── Issue
   └── Adjustment

StockMovement
   │
   └── StockAllocation
             │
             └── StockLot
```

Trong đó:

* **Product**: thông tin sản phẩm, không quản lý số lượng tồn.
* **Warehouse**: kho hàng.
* **Stock**: số lượng tồn hiện tại của sản phẩm trong từng kho.
* **StockLot**: lượng hàng còn lại theo từng lần nhập.
* **StockMovement**: lịch sử biến động tồn kho.
* **StockAllocation**: ghi nhận các StockLot được sử dụng cho một lần xuất kho.
* **InventoryService**: lớp nghiệp vụ trung tâm quản lý các thay đổi tồn kho.

Tồn kho phải luôn đảm bảo:

```text
Stock.quantity_on_hand
=
SUM(StockLot.quantity_remaining)
```

Nghiệp vụ xuất kho hiện sử dụng **FIFO** dựa trên thời điểm nhập hàng.

## Cấu trúc repository

```text
├── app/          # Source code chính
├── database/     # Migrations, seeders, ...
├── routes/       # Application routes
├── resources/    # Giao diện / tài nguyên
├── tests/        # Automated tests
├── docs/         # Tài liệu kiến trúc, database và nghiệp vụ
├── .vscode/      # Cấu hình VS Code
├── CLAUDE.md     # Hướng dẫn cho Claude
├── AGENTS.md     # Hướng dẫn cho Codex/AI agents
└── README.md
```

## Tài liệu

Tài liệu kiến trúc và quy tắc phát triển được lưu trong thư mục [`docs/`](docs/).

Các AI agent cần đọc tài liệu trong `docs/ai/` trước khi thực hiện thay đổi quan trọng đối với project.

---

**Project:** DATN2026-VS2
**Tên:** Đồ án quản lý kho
**Mục đích:** Phục vụ nghiên cứu, phát triển và hoàn thiện đồ án tốt nghiệp.
