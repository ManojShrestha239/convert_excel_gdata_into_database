<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Transfer Success</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --success-bg: #f0fdf4;
            --success-primary: #10b981;
            --success-dark: #047857;
        }

        body {
            background-color: var(--success-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        .success-card {
            border: none;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .success-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .checkmark {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: block;
            stroke-width: 4;
            stroke: #fff;
            stroke-miterlimit: 10;
            margin: 0 auto;
            box-shadow: 0 0 0 rgba(16, 185, 129, 0.4);
            animation: fill 0.4s ease-in-out 0.4s forwards, scale 0.3s ease-in-out 0.9s both;
        }

        .checkmark__circle {
            stroke-dasharray: 166;
            stroke-dashoffset: 166;
            stroke-width: 4;
            stroke-miterlimit: 10;
            stroke: var(--success-primary);
            fill: none;
            animation: stroke 0.6s cubic-bezier(0.65, 0, 0.45, 1) forwards;
        }

        .checkmark__check {
            transform-origin: 50% 50%;
            stroke-dasharray: 48;
            stroke-dashoffset: 48;
            animation: stroke 0.3s cubic-bezier(0.65, 0, 0.45, 1) 0.8s forwards;
        }

        @keyframes stroke {
            100% {
                stroke-dashoffset: 0;
            }
        }

        @keyframes scale {

            0%,
            100% {
                transform: none;
            }

            50% {
                transform: scale3d(1.1, 1.1, 1);
            }
        }

        @keyframes fill {
            100% {
                box-shadow: inset 0 0 0 100vh var(--success-primary);
            }
        }

        .btn-success {
            background-color: var(--success-primary);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            background-color: var(--success-dark);
            transform: translateY(-2px);
        }

        .card-header {
            background: linear-gradient(135deg, var(--success-primary), var(--success-dark));
            border-bottom: none;
            padding: 2rem;
        }

        .card-body {
            padding: 3rem;
        }
    </style>
</head>

<body>
    <div class="container min-vh-100 d-flex align-items-center justify-content-center">
        <div class="col-lg-6">
            <div class="success-card card shadow-lg">
                <div class="card-header text-white text-center">
                    <h3 class="fw-bold mb-0">Data Transfer Successful</h3>
                </div>
                <div class="card-body text-center">
                    <svg class="checkmark mb-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                        <circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
                        <path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" />
                    </svg>
                    <h4 class="fw-bold mb-3">All data transferred successfully!</h4>
                    <p class="text-muted mb-4">Your Excel data has been securely imported to the database. You can now
                        access it from your dashboard.</p>
                    <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                        <a href="{{ url('/') }}" class="btn btn-success btn-lg px-4 gap-3">
                            <i class="bi bi-speedometer2 me-2"></i> Go to Dashboard
                        </a>
                    </div>
                </div>
                <div class="card-footer bg-transparent text-center py-3">
                    <small class="text-muted">Process completed at <span id="timestamp"></span></small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Display current timestamp
        document.getElementById('timestamp').textContent = new Date().toLocaleString();

        // Add confetti effect on load
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof confetti === 'function') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: {
                        y: 0.6
                    }
                });
            }
        });
    </script>
    <!-- Optional: Add confetti library for celebration effect -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.5.1/dist/confetti.browser.min.js"></script>
</body>

</html>
