<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk Laravel Filesystem dùng để lưu media. Giai đoạn 1 dùng 'public'
    | (storage/app/public, phục vụ qua symlink public/storage).
    |
    | Đổi sang S3/R2 sau này chỉ cần đổi MEDIA_DISK trong .env + cấu hình
    | thêm disk tương ứng trong config/filesystems.php — không sửa code
    | Service/Repository vì mọi nơi đều đọc qua config('media.disk').
    |
    */

    'disk' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | Thư mục gốc chứa toàn bộ media trên disk ở trên.
    |
    | Cấu trúc thư mục con (media/{year}/{month}/{uuid}/...) là quy ước cố định
    | được implement trong MediaService (STEP 8), không đưa vào config dạng
    | pattern string vì sẽ cần thêm 1 tầng parser không cần thiết cho một
    | cấu trúc không đổi theo môi trường.
    |
    */

    'base_path' => env('MEDIA_BASE_PATH', 'media'),

    /*
    |--------------------------------------------------------------------------
    | Max File Size (KB)
    |--------------------------------------------------------------------------
    |
    | Đơn vị KB — khớp trực tiếp với rule 'max' của Laravel Validator, dùng
    | thẳng trong MediaUploadRequest, không cần convert.
    |
    | audio/file: đã khai báo sẵn để mở rộng sau này (theo yêu cầu giai đoạn 1),
    | nhưng KHÔNG được coi là type hợp lệ cho upload cho tới khi 'types' bên
    | dưới bật extensions/mimes tương ứng.
    |
    */

    'max_size' => [
        'image'    => env('MEDIA_MAX_SIZE_IMAGE', 5 * 1024),     // 5MB
        'video'    => env('MEDIA_MAX_SIZE_VIDEO', 50 * 1024),    // 50MB
        'document' => env('MEDIA_MAX_SIZE_DOCUMENT', 10 * 1024), // 10MB
        'audio'    => env('MEDIA_MAX_SIZE_AUDIO', 20 * 1024),    // dự phòng, chưa dùng
        'file'     => env('MEDIA_MAX_SIZE_FILE', 10 * 1024),     // dự phòng, chưa dùng
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Types
    |--------------------------------------------------------------------------
    |
    | Mỗi type khai báo cả 'extensions' lẫn 'mimes'. MediaUploadRequest sẽ
    | validate CẢ HAI (không chỉ tin MIME client gửi lên, không chỉ tin đuôi
    | file) — đúng yêu cầu bảo mật ở mục XXII.
    |
    | type nào có 'extensions' rỗng nghĩa là CHƯA cho phép upload loại đó ở
    | giai đoạn 1 (audio, file) dù đã có chỗ để bật lên sau này.
    |
    */

    'types' => [
        'image' => [
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
            'mimes' => ['image/jpeg', 'image/png', 'image/webp'],
        ],
        'video' => [
            'extensions' => ['mp4', 'webm'],
            'mimes' => ['video/mp4', 'video/webm'],
        ],
        'document' => [
            'extensions' => ['pdf'],
            'mimes' => ['application/pdf'],
        ],
        'audio' => [
            'extensions' => [], // dự phòng, chưa cho phép upload
            'mimes' => [],
        ],
        'file' => [
            'extensions' => [], // dự phòng, chưa cho phép upload
            'mimes' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing
    |--------------------------------------------------------------------------
    |
    | webp_quality: 0-100, dùng cho encode() ở ImageProcessor (STEP 7).
    |
    | variants: tên => width đích (px). Chiều cao luôn tự tính theo tỉ lệ gốc
    | (Intervention Image scaleDown() tự đảm bảo giữ aspect ratio + không
    | upscale — đã test PASS ở STEP 2, không viết lại logic này).
    |
    */

    'image' => [
        'webp_quality' => env('MEDIA_WEBP_QUALITY', 82),

        'variants' => [
            'thumbnail' => 400,
            'medium' => 800,
            'large' => 1200,
            'xlarge' => 1600,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Capabilities (per type)
    |--------------------------------------------------------------------------
    |
    | Cờ bật/tắt xử lý theo từng loại media. MediaService đọc từ đây để quyết
    | định luồng xử lý, KHÔNG hard-code if/else theo type trong Service.
    |
    | Giai đoạn 1: chỉ image có generate_webp/generate_variants = true.
    | Video/document chỉ lưu original, không xử lý gì thêm.
    |
    | Khi nào làm video processing thật (transcode, thumbnail từ frame...),
    | chỉ cần bật flag tương ứng + viết VideoProcessor — không sửa MediaService.
    |
    */

    'processing' => [
        'image' => [
            'generate_webp' => true,
            'generate_variants' => true,
        ],
        'video' => [
            'generate_webp' => false,
            'generate_variants' => false,
            'transcode' => false, // dự phòng giai đoạn 5
        ],
        'document' => [
            'generate_webp' => false,
            'generate_variants' => false,
            'extract_content' => false, // dự phòng — vd đếm số trang PDF
        ],
        'audio' => [
            'generate_webp' => false,
            'generate_variants' => false,
        ],
        'file' => [
            'generate_webp' => false,
            'generate_variants' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status Workflow
    |--------------------------------------------------------------------------
    |
    | Danh sách trạng thái hợp lệ của 1 media record. Cột 'status' trong DB
    | là string (VARCHAR), KHÔNG phải MySQL enum — thêm trạng thái mới sau
    | này (vd khi có Queue) chỉ cần thêm dòng ở đây, không cần migration.
    |
    | Giai đoạn 1 xử lý đồng bộ nên mọi media tạo xong sẽ có status = 'ready'
    | ngay lập tức. 'pending'/'processing'/'failed' đã khai báo sẵn cho khi
    | chuyển sang xử lý qua Queue (giai đoạn 5).
    |
    */

    'statuses' => [
        'pending',    // đã lưu record, chưa xử lý xong (dự phòng Queue)
        'processing', // đang xử lý (dự phòng Queue)
        'ready',      // xử lý xong, sẵn sàng sử dụng — mặc định giai đoạn 1
        'failed',     // xử lý thất bại (dự phòng Queue)
    ],

    'default_status' => env('MEDIA_DEFAULT_STATUS', 'ready'),

];