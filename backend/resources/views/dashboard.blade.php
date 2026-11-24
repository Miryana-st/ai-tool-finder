@php($user = auth()->user())
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AI-Tool-Finder</title>
</head>
<body>

    <h1>AI-Tool-Finder</h1>

    <p>Добре дошъл, {{ $user->name }}! Ти си с роля: {{ $user->role->label() }}.</p>

    <nav aria-label="Меню">
        <ul>
            @foreach ($user->role->menu() as $item)
                <li>
                    <button type="button" data-menu-key="{{ $item['key'] }}" disabled>{{ $item['label'] }}</button>
                </li>
            @endforeach
        </ul>
    </nav>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">Изход</button>
    </form>

</body>
</html>
