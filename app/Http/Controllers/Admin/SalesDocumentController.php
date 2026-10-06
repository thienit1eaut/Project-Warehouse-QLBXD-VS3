<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SalesDocument\StoreSalesDocumentRequest;
use App\Http\Requests\SalesDocument\UpdateSalesDocumentRequest;
use App\Services\CustomerService;
use App\Services\ProductService;
use App\Services\SalesDocumentService;
use App\Services\WarehouseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * HTTP layer cho chứng từ bán. Controller KHÔNG đụng Stock/StockLot/StockMovement/StockAllocation —
 * POST đi qua SalesDocumentService -> InventoryService::issueStock().
 */
class SalesDocumentController extends Controller
{
    public function __construct(
        protected SalesDocumentService $service,
        protected CustomerService $customerService,
        protected WarehouseService $warehouseService,
        protected ProductService $productService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'status']);

        return Inertia::render('Admin/SalesDocuments/Index', [
            'pageTitle' => 'Bán hàng',
            'documents' => $this->service->list($filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/SalesDocuments/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Tạo chứng từ bán hàng',
            'document' => null,
        ]));
    }

    public function store(StoreSalesDocumentRequest $request): RedirectResponse
    {
        $document = $this->service->createDraft($request->validated(), $request->user()->id);

        return redirect()->route('admin.sales.show', $document->id)
            ->with('success', 'Đã tạo chứng từ bán hàng (nháp).');
    }

    public function show(int $salesDocument): Response
    {
        $document = $this->service->findDetail($salesDocument);

        return Inertia::render('Admin/SalesDocuments/Show', [
            'pageTitle' => 'Chứng từ bán ' . $document->document_code,
            'document' => $document,
        ]);
    }

    public function edit(int $salesDocument): Response|RedirectResponse
    {
        try {
            $document = $this->service->findEditable($salesDocument);
        } catch (ValidationException $e) {
            return redirect()->route('admin.sales.show', $salesDocument)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return Inertia::render('Admin/SalesDocuments/Form', array_merge($this->formOptions(), [
            'pageTitle' => 'Sửa chứng từ ' . $document->document_code,
            'document' => $document,
        ]));
    }

    public function update(UpdateSalesDocumentRequest $request, int $salesDocument): RedirectResponse
    {
        $this->service->updateDraft($salesDocument, $request->validated());

        return redirect()->route('admin.sales.show', $salesDocument)
            ->with('success', 'Đã cập nhật chứng từ bán hàng.');
    }

    public function destroy(int $salesDocument): RedirectResponse
    {
        try {
            $this->service->deleteDraft($salesDocument);
        } catch (ValidationException $e) {
            return redirect()->route('admin.sales.show', $salesDocument)
                ->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('admin.sales.index')
            ->with('success', 'Đã xoá chứng từ nháp.');
    }

    public function post(Request $request, int $salesDocument): RedirectResponse
    {
        $this->service->post($salesDocument, $request->user()->id);

        return redirect()->route('admin.sales.show', $salesDocument)
            ->with('success', 'Đã xuất kho thành công.');
    }

    private function formOptions(): array
    {
        return [
            'customerOptions' => $this->customerService->options(),
            'warehouseOptions' => $this->warehouseService->options(),
            'productOptions' => $this->productService->options(),
            'today' => now()->toDateString(),
        ];
    }
}