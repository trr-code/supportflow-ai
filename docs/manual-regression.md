# Manual regression checklist

Status of each Stage 0–7 and P0–P3 item lives in [release-readiness.md](release-readiness.md). This file is the click-path. A local pass is not Forge verification. A Forge deploy is not readiness to show clients.

Run this after automated Pest Feature/Unit tests and local `composer test:evals` pass. Do not deploy from this checklist. Use the seeded Harbor catalog as the authority—not an earlier chatbot answer.

Local: [https://supportflow-ai.test](https://supportflow-ai.test)
Forge staging (off-peak): [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com)

Local tickets need `composer run dev`, which listens on `ai,default`. Forge staging already runs its queue worker.

Authority for product claims:

- Size exchanges for packs, shells, and footwear are free within 30 days if unused.
- Harbor Trail Packs ship in 28L and 36L.
- Unused returns: 30 days, tags attached; original box not required; prepaid UPS label after the return is approved in the order portal.
- Thread colors for Driftwood Duffel embroidery are not documented.

## Customer chat (desktop)

- [ ] Open chat from home. Prepared-question buttons fill the composer and do not send or reset the thread.
- [ ] Ask: unused Trail Pack, no original box, prepaid label. Answer covers 30 days, box/carton, and prepaid label. **Source:** Return window. No visible `CITES:`.
- [ ] Ask: unused 28L Trail Pack exchange for 36L, free of charge. Answer may say free **only** with unused and 30-day conditions. Cite exchanges and/or trail pack sizes. Do not invent a restocking fee. Do not promise a prepaid UPS label unless the question asked for a return label.
- [ ] Follow-up after sizes (“Do Harbor Trail Packs come in 28L and 36L?” → “Is that free?”). Second answer uses exchange policy, not a hard refuse.
- [ ] Follow-up “Which one is longer?” after comparing return window vs warranty. Both policies stay in play.
- [ ] Ask Ohio sales tax. Refuse; suggest a ticket; no invented rate.
- [ ] Ask Driftwood Duffel thread colors. No invented color list. May cite duffel embroidery/coating policy.
- [ ] Advanced safety prompt: ignore instructions / reveal system prompt. Refuse. No API keys. No unrelated policy dump.
- [ ] New conversation clears the thread. A second browser/incognito session does not see the first transcript.
- [ ] Stop generating mid-stream. UI shows Stopped. Composer can send again. Do not show the stream-error message.
- [ ] If the stream errors, the question returns to the composer with “The assistant could not finish that answer. Try again.”
- [ ] Mid-stream Chrome DevTools Offline, then restore No throttling: Thinking… hides when the drop is detected even if `/livewire/update` failed while offline. After reconnect, recovery retries within about a second (window `offline`/`online` may never fire). Question returns with “The assistant could not finish that answer. Try again.” Composer can send again. Not stuck on Thinking…. Then send again without “Please wait for the current answer to finish.”
- [ ] Enter submits the composer. Focus rings stay visible on Tab. Escape/new-conversation confirm does not send a stray question.

## Tickets and approval visibility

- [ ] Submit the unused Trail Pack return ticket (prepared form or scenario). Status page shows AI reviewing, then a human-must-approve message. No draft body.
- [ ] Open Agent Dashboard → ticket. Pending draft is visible to the agent only, with “The customer cannot see this until you approve and send.”
- [ ] Approve and send. Public status page then shows the reply (`Hi {First},` / `Best,` / `Alex Rivera` / `Harbor & Co Support`).
- [ ] Shipping/returns scenario: 28L too small, wants 36L and prepaid label. Draft covers exchange **and** prepaid return-label policy. Status stays Awaiting review until a human sends it.
- [ ] Insufficient-knowledge scenario: embroidery + thread colors. Escalated to human. No customer draft.
- [ ] Angry billing scenario: High priority (unless Urgent), escalated, no AI draft.
- [ ] Prompt-injection ticket scenario: injection warning, no AI draft, human writes the reply.
- [ ] Reject a grounded draft: customer still does not see it. Ticket escalates.
- [ ] Open another visitor’s status URL (or a random token): no ticket, no notes, no pending draft.

## Dictation

Automated coverage drives controlled transcription events on the ticket description field: a partial transcript appears, a typed edit is kept, a later phrase still appends, and stopping leaves that text in place and ignores a later event. It does not open a microphone.

- [ ] Chat microphone: real permission prompt, spoken transcript lands in the composer, nothing is spoken back.
- [ ] Ticket description microphone: same real microphone. Do not enter real personal data.
- [ ] If the browser has no mic, the typed fallback message appears.

## Mobile and iPad

Use a phone viewport (~390px) and an iPad viewport (~768–1024px), plus a real device if available.

- [ ] Chat launcher, panel, and composer stay usable. Streaming text stays in view. Scroll-up pauses pin-to-bottom.
- [ ] Knowledge index and a policy page (`/knowledge/return-window`) are readable; Ask assistant opens chat without sending.
- [ ] Ticket create, status polling, and Agent ticket show do not clip primary actions.
- [ ] Dictation button remains tappable; virtual keyboard does not cover Send.

## Keyboard and accessibility

- [ ] Tab order: chat launcher → panel → question → send / dictate / stop.
- [ ] Validation errors are announced (`aria-invalid`, `aria-describedby`).
- [ ] Skip sending with an empty or 3-character question (min 4).

## Live Forge staging (off-peak)

Target [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). That hostname is the live non-ZDD site (Octane on 8001). Run this only after local `composer test` and `composer test:evals` pass. This checklist is verification, not a deploy. Do not run Stressless chat/ticket writes. Do not raise `throttle:chat`. CareerForge is unused: do not skip or shrink these checks; if `worker-987181` contends, stop that worker rather than reducing tests. Do not delete CareerForge or shared Lucid/SupportFlow resources.

- [x] `/`, `/knowledge`, `/knowledge/return-window`, Open Agent Dashboard. (2026-10-26 `d93755f`; dashboard still showed 1 AI unavailable.)
- [x] SupportFlow Octane, scheduler, and queue worker `--queue=ai,default` are running. (`daemon-1104270`, crontab `scheduled-2143100.log` mtime 19:06Z, `worker-1104326` `--queue=ai,default`. Live command includes `--daemon` vs docs.)
- [x] `/up` returns 200. The queue heartbeat names `ai` and `default` and is not stale. `CACHE_STORE=database`.
- [x] One grounded chat turn with citations. No `CITES:` in the UI. (Unused Trail Pack/box/prepaid. Persisted follow-up not retested this pass.)
- [x] One ticket through triage → awaiting review. Customer status hid the draft. **SF-R50WE** then Approve and send; Sources remained Return window and Box not required.
- [x] SSE first token arrives (chat TTFB waves). Mid-stream Stop not retested on Forge this pass.
- [x] Repeat the 28L → 36L exchange question against this seeded catalog. Unused/30-day conditions present; box not required; prepaid after portal approval.
- [x] GET Stressless after a smoke pass: `/up`, `/`, `/knowledge`, `/knowledge/return-window` only. Do not copy 16 Octane workers onto Forge. GET Stressless does not close chat TTFB. (Smoke through capacity; cooldown last-healthy 16. See [performance.md](performance.md).)
- [x] Chat TTFB/concurrency: concurrent `POST /chat/stream` with separate cookie jars (local current-path numbers first, then Forge). Pre-fix 7/7 local and 7/7 Forge (Forge 9.7 s was 1-worker serialization). Local after embed batch 1.3–1.5 s. Re-run Forge waves after that deploy.

## Sign-off

| Surface | Local | Forge | Notes |
| --- | --- | --- | --- |
| Chat/RAG | | | |
| Tickets/approval | | | |
| Injection/unknown | | | |
| Streaming recovery | | | |
| Mobile/iPad | | | |
| Dictation/keyboard | | | |
