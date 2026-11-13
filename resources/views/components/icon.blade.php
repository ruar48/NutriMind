<svg {{ $attributes->class(['shrink-0'])->merge([
    'xmlns' => 'http://www.w3.org/2000/svg',
    'width' => $size,
    'height' => $size,
    'viewBox' => '0 0 24 24',
    'fill' => 'none',
    'stroke' => 'currentColor',
    'stroke-width' => $strokeWidth,
    'stroke-linecap' => 'round',
    'stroke-linejoin' => 'round',
    'data-icon' => $name,
]) }} @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif @if ($isFallback()) data-icon-fallback @endif>{!! $markup() !!}</svg>
