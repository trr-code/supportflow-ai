---
paths:
  - resources/views/components/dictation-button.blade.php
  - 'resources/views/components/**'
---

# Components

## One active recorder per browser tab
Starting dictation in a composer claims recording ownership and immediately mutes/stops the previous capture while allowing its final transcript to settle. Preserve typed edits and proper names; do not force sentence capitalization mid-utterance or cut continuous speech into four-second turns. This is the owner's UX choice, not a universal browser requirement.

## Microphone startup is accessible and cannot cancel itself
While connecting, use the existing busy control and nonvisual status; disable repeated start clicks without routine visible status popups. Keep acquired capture/connection references immediately, release them on failure/cancel, bound network and channel-open waits, and enter recording only after the data channel opens. Scope event callbacks to their recorder session. Physical microphone quality remains separate from a real-provider fictional-audio integration pass.

## Dictation uses a page-independent recorder with two-stage presentation
Current owner UX: first empty-draft recording shows a composer-width waveform; Stop finalizes an editable draft; resumed recording retains that draft with live incoming text above the waveform. One tab-local engine owns capture across wire:navigate and external focus changes; views attach/detach without releasing it. Text stays in the originating conversation/context. Explicit Stop, recorder handoff, limits/failure, reset and page unload release capture appropriately; sending never resumes old text. No spoken output or cross-page banner. Use stable conversation/context IDs, session-scoped callbacks and preserve typed edits/reduced motion.

## Routine microphone feedback stays visual and quiet
Owner 2026-11-21 rejects routine visible Waiting/Connecting/Recording/Select Stop messages. Use existing controls and waveform for ordinary states; retain accessible names/nonvisual status and actionable permission/connection errors. Waveform should be much slower and calmer than the current rapid equal-height bars: subtle dotted center baseline with organized speech peaks, using Lucid colors. This is owner reference-driven styling, not an official OpenAI animation specification; the visual refinement remains subject to owner review.

## Explicit Send can finish dictation and submit once
Owner approved 2026-11-21: while recording, explicit Send stops capture, waits for finalized transcription and submits exactly once. Stop remains finalize-and-review without sending. Do not send interim text, continue capture after submission, duplicate/restored sent text or submit on transcription failure; preserve the draft on failure. This supersedes the blanket blocked-Send-during-recording rule; keep repeated Send blocked during finalization. Do not confuse this owner choice with a universal industry/OpenAI mandate.

## Anonymous presentation components are the default
Default to anonymous Blade presentation components with explicit @props and attributes forwarded/merged onto their intended elements. Keep workflow state in the calling Livewire component. Class-based Blade components remain available when their capabilities are warranted; this does not require converting Livewire components.

## Status pills use stronger pastel semantics with black outlines
Owner 2026-11-23 keeps black inset outlines and readable dark labels/shapes, but wants more distinctive pastel status fills. Shared status and success pills use blue/amber/orange/red/emerald/zinc 200 fills: information/progress, waiting/attention, escalation, failure, success, neutral respectively. Priority medium/high/urgent follows golden amber/peach-orange/red with a black outline; instruction examples reuse shared status components. Match actual roles consistently; W3C requires contrast and non-color meaning, not a specific palette. Carbon/GOV.UK status guidance supports these conventional meanings; exact Lucid tones are owner-authorized design choices.
