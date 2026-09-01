# Livewire loop keys

Every `@foreach` / `@forelse` that renders Livewire-updated DOM must put a **stable unique** `wire:key` on the loop’s first real DOM element.

* Prefer a model or business id (e.g. `wire:key="kb-article-{{ $article->id }}"`).
* Prefix keys when multiple loops share a component so ids cannot collide.
* When keying a Blade or Flux component in a loop, pass `wire:key` and **verify** the component root merges `$attributes` so the key reaches the DOM.
* Prefer identity keys over `$loop->index`. For string lists without DB ids, key from a hash of the value — `wire:key="kb-heading-{{ $article->id }}-{{ hash('sha256', $heading) }}"` — not the index and not a raw secret in the attribute.
* Enum / option loops are not exempt. Still pass a prefixed `wire:key`.
* Also key the first element of each `@switch` / `@case` branch when the active branch can change across Livewire requests.
* Nested `<livewire:*>` inside a loop still needs its own `:wire:key`, even when the wrapper already has `wire:key`.
* For `<livewire:dynamic-component>` / `<livewire:is>`, use a `:wire:key` that changes with the active component (or whenever you intentionally remount a child).
* On dependent selects (options rebuild from another field), put `wire:key` on the dependent `<select>` so the bound value remounts/resets cleanly.
* Plain `@if` / `@else` that only toggles visibility of stable markup does not require `wire:key`; key when swapping distinct Livewire children in the same slot.
