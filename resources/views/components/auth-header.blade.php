@props (['title', 'description',])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl" level="1" class="lucid-main-heading">{{ $title }}</flux:heading>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
