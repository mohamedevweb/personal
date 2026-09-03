type RefreshUser = () => Promise<unknown>
type EnterFeed = () => unknown

/** Refreshes profile details created during onboarding before the app shell renders. */
export async function enterAppAfterOnboarding(refreshUser: RefreshUser, enterFeed: EnterFeed) {
  try {
    await refreshUser()
  } catch {
    // Onboarding is already complete. A transient refresh failure must not trap
    // the creator here; the authenticated shell can retry on a later request.
  }

  return enterFeed()
}
