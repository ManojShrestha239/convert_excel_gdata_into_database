<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tint Project | Excel Upload</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #3f37c9;
            --light: #f8f9fa;
            --dark: #212529;
            --success: #4cc9f0;
            --card-bg: rgba(255, 255, 255, 0.96);
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .form-container {
            max-width: 520px;
            margin: 2rem auto;
            padding: 2.5rem;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: perspective(1000px) rotateX(5deg);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        }

        .form-container:hover {
            transform: perspective(1000px) rotateX(0deg);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .form-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
        }

        .form-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--success);
            border-radius: 2px;
            animation: underlineExpand 0.6s ease-out forwards;
        }

        @keyframes underlineExpand {
            from {
                width: 0;
                opacity: 0;
            }

            to {
                width: 50px;
                opacity: 1;
            }
        }

        .form-control {
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 12px 16px;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.9rem;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
            position: relative;
            overflow: hidden;
            transition: all 0.4s ease;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .btn-primary:hover::after {
            left: 100%;
        }

        .file-upload-wrapper {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            border: 2px dashed rgba(67, 97, 238, 0.3);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            border-color: var(--primary);
            background-color: rgba(67, 97, 238, 0.05);
        }

        .file-upload-label i {
            font-size: 2rem;
            color: var(--primary);
            margin-right: 1rem;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover i {
            transform: scale(1.1);
        }

        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .sample-link {
            display: inline-flex;
            align-items: center;
            color: var(--primary);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .sample-link:hover {
            color: var(--secondary);
            transform: translateX(5px);
        }

        .sample-link i {
            margin-left: 5px;
            transition: all 0.3s ease;
        }

        .sample-link:hover i {
            transform: translateX(3px);
        }

        .error-message {
            animation: shake 0.5s ease;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            20%,
            60% {
                transform: translateX(-5px);
            }

            40%,
            80% {
                transform: translateX(5px);
            }
        }

        .floating-icon {
            position: absolute;
            opacity: 0.1;
            z-index: -1;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
            }

            50% {
                transform: translateY(-20px) rotate(5deg);
            }

            100% {
                transform: translateY(0) rotate(0deg);
            }
        }

        .icon-1 {
            top: 10%;
            left: 10%;
            font-size: 3rem;
            color: var(--primary);
            animation-delay: 0s;
        }

        .icon-2 {
            bottom: 15%;
            right: 10%;
            font-size: 4rem;
            color: var(--secondary);
            animation-delay: 0.5s;
        }

        .icon-3 {
            top: 60%;
            left: 20%;
            font-size: 2.5rem;
            color: var(--success);
            animation-delay: 1s;
        }

        .alert {
            border-radius: 8px;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background-color: rgba(76, 201, 240, 0.15);
            color: #0d6efd;
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .is-invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .border-danger {
            border-color: #dc3545 !important;
        }
    </style>
</head>

<body>
    <div class="container py-5">
        <!-- Floating background icons -->
        <i class="fas fa-file-excel floating-icon icon-1"></i>
        <i class="fas fa-database floating-icon icon-2"></i>
        <i class="fas fa-cloud-upload-alt floating-icon icon-3"></i>

        <div class="animate__animated animate__fadeInUp animate__faster form-container">
            <h4 class="form-title text-center animate__animated animate__fadeIn">Old Style Database Upload</h4>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show animate__animated animate__fadeIn"
                    role="alert">
                    <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show animate__animated animate__fadeIn"
                    role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form action="{{ route('oldstyleExcel.store') }}" method="post" enctype="multipart/form-data"
                class="animate__animated animate__fadeIn animate__delay-1s">
                @csrf

                <div class="mb-4">
                    <label for="database_name" class="form-label fw-medium">Database Name <span
                            class="text-danger">*</span></label>
                    <input type="text" name="database_name" id="database_name"
                        class="form-control shadow-sm @error('database_name') is-invalid @enderror"
                        placeholder="Enter your database name" value="{{ old('database_name') }}" required>
                    @error('database_name')
                        <div class="invalid-feedback error-message">
                            <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label class="form-label fw-medium">Excel File Upload <span class="text-danger">*</span></label>
                    <div class="file-upload-wrapper">
                        <label class="file-upload-label @error('excel') border-danger @enderror">
                            <i class="fas fa-file-import"></i>
                            <span id="file-name">Choose or drag & drop your Excel file</span>
                            <input type="file" name="excel" id="excel" class="file-upload-input"
                                accept=".xlsx,.xls" required>
                        </label>
                    </div>
                    <div class="mt-2">
                        <a href="{{ asset('sample-excel/Sample-Gdata.xlsx') }}" class="sample-link" download>
                            Download sample file <i class="fas fa-download"></i>
                        </a>
                    </div>
                    @error('excel')
                        <div class="text-danger mt-2 small error-message">
                            <i class="fas fa-exclamation-circle me-1"></i> {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-upload me-2"></i> Upload & Process
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // File input change handler
            $('#excel').on('change', function() {
                const fileName = $(this).val().split('\\').pop();
                const fileInput = this.files[0];

                if (fileName) {
                    // Validate file type
                    const allowedExtensions = /(\.xlsx|\.xls|\.csv)$/i;
                    if (!allowedExtensions.exec(fileName)) {
                        $('#file-name').html(
                            '<span class="text-danger">Please upload a valid Excel file (.xlsx, .xls, .csv)</span>'
                        );
                        $(this).val('');
                        $('.file-upload-label').css({
                            'border-color': '#dc3545',
                            'background-color': 'rgba(220, 53, 69, 0.05)'
                        });
                        return;
                    }

                    // Validate file size (10MB)
                    if (fileInput && fileInput.size > 10485760) {
                        $('#file-name').html(
                            '<span class="text-danger">File size must not exceed 10MB</span>');
                        $(this).val('');
                        $('.file-upload-label').css({
                            'border-color': '#dc3545',
                            'background-color': 'rgba(220, 53, 69, 0.05)'
                        });
                        return;
                    }

                    $('#file-name').html(`<strong>${fileName}</strong>`);
                    $('.file-upload-label').css({
                        'border-color': '#4361ee',
                        'background-color': 'rgba(67, 97, 238, 0.05)'
                    });
                } else {
                    $('#file-name').text('Choose or drag & drop your Excel file');
                }
            });

            // Database name validation
            $('#database_name').on('input', function() {
                const value = $(this).val();
                const regex = /^[a-zA-Z0-9_]*$/;

                if (!regex.test(value)) {
                    $(this).addClass('is-invalid');
                    if (!$(this).next('.invalid-feedback').length) {
                        $(this).after(
                            '<div class="invalid-feedback"><i class="fas fa-exclamation-circle me-1"></i>Only letters, numbers, and underscores are allowed.</div>'
                        );
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    $(this).next('.invalid-feedback:not([data-server])').remove();
                }
            });

            // Drag and drop functionality
            const fileUploadLabel = $('.file-upload-label')[0];

            fileUploadLabel.addEventListener('dragover', (e) => {
                e.preventDefault();
                $(fileUploadLabel).css({
                    'border-color': '#4361ee',
                    'background-color': 'rgba(67, 97, 238, 0.1)',
                    'transform': 'scale(1.02)'
                });
            });

            fileUploadLabel.addEventListener('dragleave', () => {
                $(fileUploadLabel).css({
                    'border-color': 'rgba(67, 97, 238, 0.3)',
                    'background-color': 'rgba(255, 255, 255, 0.5)',
                    'transform': 'scale(1)'
                });
            });

            fileUploadLabel.addEventListener('drop', (e) => {
                e.preventDefault();
                $(fileUploadLabel).css({
                    'border-color': 'rgba(67, 97, 238, 0.3)',
                    'background-color': 'rgba(255, 255, 255, 0.5)',
                    'transform': 'scale(1)'
                });

                if (e.dataTransfer.files.length) {
                    $('#excel')[0].files = e.dataTransfer.files;
                    const fileName = e.dataTransfer.files[0].name;
                    $('#file-name').html(`<strong>${fileName}</strong>`);
                    $('#excel').trigger('change');
                }
            });

            // Form submission animation
            $('form').on('submit', function(e) {
                // Basic validation check
                const databaseName = $('#database_name').val().trim();
                const fileInput = $('#excel')[0].files[0];

                if (!databaseName || !fileInput) {
                    e.preventDefault();

                    if (!databaseName) {
                        $('#database_name').addClass('is-invalid');
                    }
                    if (!fileInput) {
                        $('.file-upload-label').css('border-color', '#dc3545');
                        $('#file-name').html('<span class="text-danger">Please select a file</span>');
                    }
                    return false;
                }

                $('.btn-primary').html(
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...'
                );
                $('.btn-primary').prop('disabled', true);

                // Add a loading animation to the form container
                $(this).css('opacity', '0.8');
            });
        });
    </script>
</body>

</html>
