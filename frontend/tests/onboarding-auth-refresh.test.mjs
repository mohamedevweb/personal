import assert from 'node:assert/strict'
import test from 'node:test'

import { enterAppAfterOnboarding } from '../app/utils/onboarding.ts'

test('refreshes the authenticated profile before entering the feed', async () => {
  const events = []

  await enterAppAfterOnboarding(
    async () => {
      events.push('refreshing')
      await Promise.resolve()
      events.push('refreshed')
    },
    () => events.push('entered')
  )

  assert.deepEqual(events, ['refreshing', 'refreshed', 'entered'])
})

test('does not trap an onboarded creator when the profile refresh fails', async () => {
  let entered = false

  await enterAppAfterOnboarding(
    async () => { throw new Error('temporary API failure') },
    () => { entered = true }
  )

  assert.equal(entered, true)
})
