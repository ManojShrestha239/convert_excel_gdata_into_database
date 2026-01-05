<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Tint Project</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <form action="{{ route('download') }}" class="form" method="POST">
            @csrf
            <button class="btn-primary">
                Download
            </button>
        </form>
    </div>
</body>
</html>
