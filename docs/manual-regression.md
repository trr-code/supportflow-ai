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

## Microphone walkthrough — current owner decisions 2026-11-21

Explicitly started dictation continues across internal browsing and external-app focus, retaining speech in its originating draft until Stop, explicit Send or a documented limit/failure. Consult the latest readiness checkpoint first; preserve the existing workspace and do not repeat accepted checks just because they appear in an older checklist.

- [x] Terry confirmed the spoken navigation narrative remained in Document chat after policies, Agent desk tab, Invoice review and Customer support browsing. This is the exercised path, not every browser/device.
- [x] Terry confirmed Stop/resume kept the earlier draft and appended one additional sentence exactly once.
- [ ] Samsung owner observation 2026-11-23 used Android Recents to leave the browser entirely: pre-switch speech retained, background phrase absent, recording still active on return. Preserve this observation without claiming a confirmed app defect or a universal browser guarantee; do not repeat/fix it as an internal-navigation failure. Desktop external-focus acceptance remains distinct.
- [x] Terry confirmed Send submitted once, recording stayed off and the cleared composer stayed empty while he continued talking (2026-11-22). Preserve this pass; do not repeat without a relevant change.
- [ ] Review the quieter dotted waveform and absence of routine status popups with the physical microphone; actionable errors remain visible. Stop still finalizes without sending.

No special cross-page recording banner has been approved. Seek official OpenAI guidance first; if it does not settle a behavior, explain that and obtain Terry's decision rather than treating an invented UI pattern as a standard.

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
- [ ] Open Agent desk → Tickets → the matching ticket. Pending draft is visible to the agent only, with “The customer cannot see this until you approve and send.”
- [ ] Approve and send. Public status page then shows the approved reply, using the current Harbor Outfitters identity and appropriate greeting/signature.
- [ ] Shipping/returns scenario: 28L too small, wants 36L and prepaid label. Draft covers exchange **and** prepaid return-label policy. Status stays Awaiting review until a human sends it.
- [ ] Insufficient-knowledge scenario: embroidery + thread colors. Escalated to human. No customer draft.
- [ ] Angry billing scenario: High priority (unless Urgent), escalated, no AI draft.
- [ ] Prompt-injection ticket scenario: injection warning, no AI draft, human writes the reply.
- [ ] Reject a grounded draft: customer still does not see it. Ticket escalates.
- [ ] Open another visitor’s status URL (or a random token): no ticket, no notes, no pending draft.

## Dictation

Controlled browser coverage checks first-recording waveforms, Stop/finalization, live resumed text, retained edits, conversation isolation, navigation persistence and ignored late events. The separate opt-in fictional-audio probe exercises the real provider connection and resumed live transcription; physical-device acceptance remains a manual check.

- [ ] Chat microphone: real permission prompt, spoken transcript lands in the composer, nothing is spoken back.
- [ ] Ticket description microphone: same real microphone. Do not enter real personal data.
- [ ] If the browser has no mic, the typed fallback message appears.

## Mobile and iPad

Current owner clarification (2026-11-23): this remaining device pass checks mobile/tablet UI—readability, clipping, navigation, scrolling, orientation and keyboard/control reachability. Reuse accepted functional evidence. Do not require repeating upload processing, grounding or complete ticket workflows merely to inspect their layout; use existing content where available. Repeat functional actions only when necessary to expose a device-specific state or investigate an observed defect. Samsung Ask Lucid composer/Stop/background-recording observations are recorded in the newest release-readiness checkpoint and remain unresolved; no repair is claimed.

Terry explicitly defers the complete physical-device review to the end of Package 4, after the desktop repair/design pass and availability on the independently authorized live deployment. Do not insert phone checks into the current desktop walkthrough. Automated viewport evidence does not close these physical-device items.

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

Target [https://supportflow-ai-lqojsjr5.on-forge.com](https://supportflow-ai-lqojsjr5.on-forge.com). That hostname is the live non-ZDD site (Octane on 8001). Run this only after local `composer test` and `composer test:evals` pass. This checklist is verification, not a deploy. Do not run Stressless chat/ticket writes. Do not raise `throttle:chat`. SupportFlow AI (Lucid) is the only application on this Forge server. Follow the current worker plan in release-readiness.md.

- [x] `/`, `/knowledge`, `/knowledge/return-window`, Open Agent Dashboard. (2026-10-26 `d93755f`; dashboard still showed 1 AI unavailable.)
- [x] SupportFlow Octane, scheduler, and queue worker `--queue=ai,default` are running. (`daemon-1104270`, crontab `scheduled-2143100.log` mtime 19:06Z, `worker-1104326` `--queue=ai,default`. Live command includes `--daemon` vs docs.)
- [x] `/up` returns 200. The queue heartbeat names `ai` and `default` and is not stale. `CACHE_STORE=database`.
- [x] One grounded chat turn with citations. No `CITES:` in the UI. (Unused Trail Pack/box/prepaid. Persisted follow-up not retested this pass.)
- [x] One ticket through triage → awaiting review. Customer status hid the draft. **SF-R50WE** then Approve and send; Sources remained Return window and Box not required.
- [x] SSE first token arrives (chat TTFB waves). Mid-stream Stop not retested on Forge this pass.
- [x] Repeat the 28L → 36L exchange question against this seeded catalog. Unused/30-day conditions present; box not required; prepaid after portal approval.
- [x] GET Stressless after a smoke pass: `/up`, `/`, `/knowledge`, `/knowledge/return-window` only. Do not copy 16 Octane workers onto Forge. GET Stressless does not close chat TTFB. (Smoke through capacity; cooldown last-healthy 16. See [performance.md](performance.md).)
- [x] Chat TTFB/concurrency: concurrent `POST /chat/stream` with separate cookie jars (local current-path numbers first, then Forge). After `2d6943d`: Forge 7/7 done; warm n=1 1.37 s (was 2.89 s); n=4 1.36–6.38 s still serialized on 1 worker. Local after embed batch 1.3–1.5 s. Do not add workers.

## Sign-off

| Surface | Local | Forge | Notes |
| --- | --- | --- | --- |
| Chat/RAG | | | |
| Tickets/approval | | | |
| Injection/unknown | | | |
| Streaming recovery | | | |
| Mobile/iPad | | | |
| Dictation/keyboard | | | |
