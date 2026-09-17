// resources/js/Composables/useFolderOptions.js
//
// Build dropdown options lồng cấp (indented) từ danh sách folder phẳng
// (id, name, parent_id) — dùng chung cho Folders/Create.vue, Folders/Edit.vue,
// và Media/Index.vue (bulk add-to-folder). Chạy hoàn toàn client-side, không
// cần backend tự đệ quy dựng cây, giữ API đơn giản.

/**
 * @param {Array<{id:number, name:string, parent_id:number|null}>} folders
 * @param {number|null} excludeId - loại folder này khỏi option (vd: chính nó khi edit)
 * @returns {Array<{id:number, label:string, depth:number}>}
 */
export function buildFolderOptions(folders, excludeId = null) {
    const byParent = new Map();
    folders.forEach((f) => {
        const key = f.parent_id ?? 0;
        if (!byParent.has(key)) byParent.set(key, []);
        byParent.get(key).push(f);
    });

    const result = [];

    function walk(parentKey, depth) {
        const children = byParent.get(parentKey) ?? [];
        // Sắp theo tên trong cùng 1 cấp cho dễ tìm
        children
            .slice()
            .sort((a, b) => a.name.localeCompare(b.name))
            .forEach((f) => {
                if (f.id === excludeId) return; // loại chính nó (và toàn bộ nhánh con của nó)

                result.push({
                    id: f.id,
                    depth,
                    label: (depth > 0 ? '　'.repeat(depth) + '└ ' : '') + f.name,
                });

                walk(f.id, depth + 1);
            });
    }

    walk(0, 0);

    return result;
}