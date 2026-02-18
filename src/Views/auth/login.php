<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Timetable System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify_content: center;
            height: 100vh;
        }

        .login-card {
            max-width: 400px;
            width: 100%;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background: white;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <h3 class="text-center mb-4">Timetable System</h3>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        <form action="/login" method="POST">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Login As</label>
                <select class="form-select" id="role" name="role">
                    <option value="student">Student (View Only)</option>
                    <option value="admin">Admin</option>
                    <option value="cr">CR</option>
                    <option value="gr">GR</option>
                </select>
                <script>
                    document.getElementById('role').addEventListener('change', function() {
                        if (this.value === 'student') {
                            window.location.href = '/';
                        }
                    });
                </script>
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-2">Login</button>
            <a href="/" class="btn btn-outline-secondary w-100">Continue as Student</a>
        </form>
    </div>
</body>

</html>