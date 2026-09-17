<script setup>
import { watch, onBeforeUnmount } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';

// Rich text editor tối giản cho nội dung mô tả sản phẩm (mục 4 yêu cầu).
// Chỉ bật formatting cơ bản: heading, bold/italic, list, link — không tích
// hợp upload ảnh trong editor (ngoài phạm vi, Media Library dùng riêng qua
// MediaPicker cho ảnh đại diện, không trộn vào nội dung mô tả ở Phase 1).
const props = defineProps({
    modelValue: { type: String, default: '' }, // HTML string
    placeholder: { type: String, default: 'Nhập nội dung...' },
});

const emit = defineEmits(['update:modelValue']);

const editor = useEditor({
    content: props.modelValue,
    extensions: [StarterKit],
    editorProps: {
        attributes: {
            class: 'prose prose-sm max-w-none focus:outline-none min-h-[200px] px-3 py-2',
        },
    },
    onUpdate: ({ editor }) => {
        emit('update:modelValue', editor.getHTML());
    },
});

// Đồng bộ khi modelValue đổi từ ngoài (vd load lại data Edit) mà không phải
// do chính editor này emit ra — tránh vòng lặp set lại content liên tục.
watch(() => props.modelValue, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        editor.value.commands.setContent(value, false);
    }
});

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function toggle(command) {
    if (!editor.value) return;
    command(editor.value.chain().focus());
}
</script>

<template>
    <div class="rounded-lg border border-slate-300 focus-within:ring-2 focus-within:ring-indigo-500">
        <!-- Toolbar -->
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-1.5">
            <button
                type="button"
                class="rounded px-2 py-1 text-xs font-semibold"
                :class="editor.isActive('bold') ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleBold().run()"
            >
                B
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 text-xs italic"
                :class="editor.isActive('italic') ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleItalic().run()"
            >
                I
            </button>
            <span class="mx-1 h-4 w-px bg-slate-200" />
            <button
                type="button"
                class="rounded px-2 py-1 text-xs font-medium"
                :class="editor.isActive('heading', { level: 2 }) ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                H2
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 text-xs font-medium"
                :class="editor.isActive('heading', { level: 3 }) ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                H3
            </button>
            <span class="mx-1 h-4 w-px bg-slate-200" />
            <button
                type="button"
                class="rounded px-2 py-1 text-xs"
                :class="editor.isActive('bulletList') ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleBulletList().run()"
            >
                • List
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 text-xs"
                :class="editor.isActive('orderedList') ? 'bg-indigo-100 text-indigo-700' : 'text-slate-600 hover:bg-slate-200'"
                @click="editor.chain().focus().toggleOrderedList().run()"
            >
                1. List
            </button>
            <span class="mx-1 h-4 w-px bg-slate-200" />
            <button
                type="button"
                class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200"
                @click="editor.chain().focus().undo().run()"
            >
                ↶
            </button>
            <button
                type="button"
                class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200"
                @click="editor.chain().focus().redo().run()"
            >
                ↷
            </button>
        </div>

        <EditorContent :editor="editor" />
    </div>
</template>

<style>
/* Tailwind Typography (prose) — nếu project chưa cài @tailwindcss/typography,
   style cơ bản dự phòng cho heading/list bên dưới để không bị vỡ giao diện. */
.ProseMirror h2 { font-size: 1.25rem; font-weight: 600; margin: 0.5rem 0; }
.ProseMirror h3 { font-size: 1.1rem; font-weight: 600; margin: 0.5rem 0; }
.ProseMirror ul { list-style: disc; padding-left: 1.25rem; }
.ProseMirror ol { list-style: decimal; padding-left: 1.25rem; }
.ProseMirror p { margin: 0.4rem 0; }
</style>