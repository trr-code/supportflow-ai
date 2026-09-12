---
paths:
  - app/Livewire/Pages/TicketCreate.php
  - app/Livewire/Pages/TicketShow.php
  - app/Livewire/Pages/TicketStatus.php
---

# Pages

## Silent ticket drafts clear on leave
Ticket form fields use Livewire #[Session] so a refresh restores them silently. ForgetTicketDraftWhenLeavingCreate clears those keys on GET/HEAD to any route except tickets.create. Do not show a Draft restored banner. Do not clear on POST (Livewire updates, dictation, submit).

## Poll Agent ticket show while AI work is in flight
Agent ticket show polls with wire:poll.5s.visible while the ticket is Submitted or Triaging, or a triage/suggested-reply AiRun is Queued or Running. Queue that run when dispatching intake, draft generation, regenerate, retry AI, or a scenario ticket. Stop polling in a stable state. If a pending suggested reply exists while status is still Triaging, persist Awaiting review. Do not change the customer status page poll.

## Customer status polls until an agent reply
Customer status polls through Awaiting review until a public approved Agent reply exists. The original customer message must not stop polling.

## Clear processing flashes when AI work settles
Processing flashes “Regenerating a grounded draft…” and “Retrying AI intake…” stay only while the related triage or suggested-reply run is queued or running. Clear them on poll/render once the ticket is in a stable state. Do not clear other flashes.

## Show kept-draft regenerate notice and remount editor
After regenerate settles, if the latest event is SuggestionRegenerated with unchanged true, show “The regenerated draft matched the previous one. The current draft was kept.” Do not present that result as a new draft. When the pending suggested-reply id changes, replace draftBody so the editor shows the new text.
