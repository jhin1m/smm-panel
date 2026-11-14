@if (session('status'))
    <p class="flash ok" role="status">{{ session('status') }}</p>
@endif
@if ($errors->any())
    <div class="flash bad" role="alert">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif
