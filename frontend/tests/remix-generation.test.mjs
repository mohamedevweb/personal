import assert from 'node:assert/strict'
import test from 'node:test'

import { waitForGeneratedRemix } from '../app/utils/remixGeneration.ts'

test('keeps the current screen until the generated remix is ready', async () => {
  const statuses = ['generating', 'generating', 'draft']
  let pauses = 0
  const attempts = []

  const remix = await waitForGeneratedRemix(
    async () => ({ id: 42, status: statuses.shift(), generated_content: {}, format: 'carousel' }),
    async attempt => { pauses++; attempts.push(attempt) },
    () => true
  )

  assert.equal(remix?.status, 'draft')
  assert.equal(pauses, 2)
  assert.deepEqual(attempts, [1, 2])
})

test('stops polling when the current page is left', async () => {
  let active = true

  const remix = await waitForGeneratedRemix(
    async () => {
      active = false
      return { id: 42, status: 'draft', generated_content: {}, format: 'carousel' }
    },
    async () => undefined,
    () => active
  )

  assert.equal(remix, null)
})
