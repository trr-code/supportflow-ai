---
paths:
  - app/Livewire/Pages/TicketCreate.php
---

# Pages

## Silent ticket drafts clear on leave
Ticket form fields use Livewire #[Session] so a refresh restores them silently. ForgetTicketDraftWhenLeavingCreate clears those keys on GET/HEAD to any route except tickets.create. Do not show a Draft restored banner. Do not clear on POST (Livewire updates, dictation, submit).
