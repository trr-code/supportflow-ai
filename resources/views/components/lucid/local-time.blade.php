@props (['value', 'dateOnly' => false])

@if ($value !== null)
    <time
        wire:ignore
        datetime="{{ $value->toIso8601String() }}"
        x-data
        x-init="const date = new Date($el.dateTime); $el.textContent = new Intl.DateTimeFormat(undefined, @js($dateOnly ? ['year' => 'numeric', 'month' => 'long', 'day' => 'numeric'] : ['year' => 'numeric', 'month' => 'short', 'day' => 'numeric', 'hour' => 'numeric', 'minute' => '2-digit', 'timeZoneName' => 'short'])).format(date)"
        >{{
            $value
                ->copy()
                ->utc()
                ->format($dateOnly ? 'F j, Y' : 'M j, Y, g:i A') . ' UTC'
        }}</time
    >
@endif
