
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AI-Finder</title>
</head>
<body>

    <h1>Welcome to AI-Finder</h1>
    <p>Sign in to your account</p>

    @if (session('status'))
        <div>{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div>
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}">
        @csrf

        <div>
            <label for="email">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
            >
        </div>

        <div>
            <label for="password">Password</label>
            <input
                type="password"
                id="password"
                name="password"
                required
                autocomplete="current-password"
            >
        </div>

        <div>
            <label>
                <input type="checkbox" name="remember" value="1">
                Remember me
            </label>
        </div>

        <button type="submit">Log in</button>
    </form>

</body>
</html>
