@extends('layouts.admin')

@section('title', 'Store settings')

@section('content')
<form class="adm-form" method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
    @csrf

    @foreach ($groups as $groupName => $fields)
        <div class="adm-panel">
            <h2>{{ $groupName }}</h2>
            <div class="form-grid">
                @foreach ($fields as $key => $meta)
                    @php $type = $meta['type'] ?? 'text'; @endphp

                    @if ($type === 'image')
                        @php $current = $values[$key] ?? ''; @endphp
                        <div class="field full">
                            <label>{{ $meta['label'] }}</label>
                            <div class="setting-image">
                                @if ($current)
                                    <img class="img-preview" src="{{ setting_image($key) }}" alt="{{ $key }}">
                                @endif
                                <div class="setting-image-inputs">
                                    <input type="file" name="{{ $key }}_file" accept="image/jpeg,image/png,image/webp,image/svg+xml">
                                    <input type="text" name="{{ $key }}" value="{{ Str::startsWith($current, 'http') ? $current : '' }}" placeholder="…or paste an image URL">
                                    @if ($current)
                                        <label class="switch-row" style="padding:4px 0">
                                            <input type="checkbox" name="{{ $key }}_clear" value="1"> Remove current image
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @elseif ($type === 'textarea')
                        <div class="field full">
                            <label for="{{ $key }}">{{ $meta['label'] }}</label>
                            <textarea id="{{ $key }}" name="{{ $key }}" style="min-height:80px">{{ old($key, $values[$key] ?? '') }}</textarea>
                        </div>
                    @else
                        <div class="field">
                            <label for="{{ $key }}">{{ $meta['label'] }}</label>
                            <input id="{{ $key }}" type="{{ $type === 'number' ? 'number' : 'text' }}"
                                   @if($type === 'number') step="0.01" min="0" @endif
                                   name="{{ $key }}" value="{{ old($key, $values[$key] ?? '') }}">
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    <div style="display:flex;gap:12px;position:sticky;bottom:20px">
        <button type="submit" class="btn-sm" style="box-shadow:0 10px 30px -10px rgba(30,41,33,.5)">Save all settings</button>
    </div>
</form>
@endsection
