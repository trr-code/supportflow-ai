---
paths:
  - app/Livewire/Pages/TicketCreate.php
  - app/Livewire/Pages/TicketShow.php
  - app/Livewire/Pages/TicketStatus.php
  - app/Livewire/Pages/SupplierInvoicePage.php
  - 'app/Livewire/Pages/**'
---

# Pages

## Silent ticket drafts clear on leave
Ticket form fields use Livewire #[Session] so a refresh restores them silently. ForgetTicketDraftWhenLeavingCreate clears those keys on GET/HEAD to any route except tickets.create and demo.harbor. The Harbor workspace embeds that form, so a refresh there keeps the draft. Do not show a Draft restored banner. Do not clear on POST (Livewire updates, dictation, submit).

## Poll Agent ticket show while AI work is in flight
Agent ticket show polls with wire:poll.5s.visible while the ticket is Submitted or Triaging, or a triage/suggested-reply AiRun is Queued or Running. Queue that run when dispatching intake, draft generation, regenerate, retry AI, or a scenario ticket. Stop polling in a stable state. If a pending suggested reply exists while status is still Triaging, persist Awaiting review. The customer status poll is a separate rule: it continues until a public approved Agent reply exists.

## Customer status polls until a sent reply
Customer status keeps wire:poll.5s.visible until a public approved Agent reply exists, including Escalated and AI unavailable. The original customer message must not stop polling. The visible modifier already pauses while the tab is hidden and runs again when it returns. Stop the poll once that reply is showing. Do not drop Escalated or AI unavailable from this poll as a side effect of Agent polling.

## Clear processing flashes when AI work settles
Processing flashes “Regenerating a grounded draft…” and “Retrying AI intake…” stay only while the related triage or suggested-reply run is queued or running. Clear them on poll/render once the ticket is in a stable state. Do not clear other flashes.

## Show kept-draft regenerate notice and remount editor
After regenerate settles, if the latest event is SuggestionRegenerated with unchanged true, show “The regenerated draft matched the previous one. The current draft was kept.” Do not present that result as a new draft. When the pending suggested-reply id changes, replace draftBody so the editor shows the new text.

## Invoice form uses decimal amounts and does not toast
Show and accept decimal amounts such as $32.00. Convert with InvoiceMoney string arithmetic and store integer cents. Reject floats and more than two decimal places. CSV uses readable headings and decimal amounts such as 32.00, with the currency code in its own column. JSON stays integer cents.
Do not toast on save, confirm, extraction finish, refresh, or poll. The checklist, review form, and confirmed heading already show that state. Keep validation beside the fields.

## Sample invoices use the normal review pipeline
Offer a fictional sample invoice alongside own-file upload. Both use the same upload admission, rate limits, ownership, extraction job and explicit review/confirmation/export gates; never seed extracted answers or auto-confirm the sample. Repeated sample actions must preserve an active invoice.

## Embedded walkthrough follows child polling state
Embedded ticket status notifies parent walkthrough only when approved public agent reply presence changes. Embedded TicketShow similarly signals status/pending-draft changes from existing polling. Do not add a second polling loop or full-page refresh. Guide readiness/completion must reflect actual pending drafts/approved public agent messages.

## Reply modes are presentation state; sending remains authorized
Switch manualReply locally without a server roundtrip. Keep send/save/regenerate actions authorized and validated server-side. Do not restore obsolete modal, Add to composer or server focus-retry handlers for changing editor modes.

## Ticket guide follows the current tool or workspace task
Details and Activity clicks activate their focused instructions; Sources and primary workspace tabs return to the corresponding workspace guide. Local manual/suggested reply switches clear tool guidance immediately with the existing deferred presentation state and preventScroll focus. Keep selected tool/form state and independent drafts. Persist the guide-context flag through policy draft restoration; do not add another server roundtrip or scrolling workaround for reply switches.

## Showroom Livewire page structure
Use a PHP component class and a separate Blade view for showroom pages. Account settings have their own single-file component pattern.
