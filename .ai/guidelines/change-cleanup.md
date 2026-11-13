# Required cleanup after changes and abandoned fixes

Apply this requirement to every change, across all packages. Plan cleanup before editing and complete it before declaring the work finished.

- When replacing an implementation, remove the old markup, styles, handlers, component props, state, queries and helpers that the replacement makes unused. Trace references across all consumers before removing shared code.
- When an attempted fix fails or is abandoned, remove its experimental code, workarounds, overrides, temporary scripts, logging and diagnostics before pursuing the replacement approach. Do not stack another fix over a discarded attempt.
- Preserve unrelated owner work, shared code still in use and meaningful regression tests. Follow existing approval requirements for deleting tests, dependencies, data or shared resources.
- Reconcile related active instructions with the final implementation; retain historical evidence in readiness notes rather than contradictory active guidance.
- Verify the final behavior and cleanup with the affected tests. Report what was removed and any specific remaining or blocked cleanup. A passing test alone does not prove cleanup is complete; do not claim an application-wide audit from a scoped review.
