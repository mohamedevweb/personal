import assert from 'node:assert/strict'
import test from 'node:test'

import { createFeedRotation, feedHasMore } from '../app/utils/feedPagination.ts'

const batch = (...ids) => ids.map(id => ({ id }))

test('a batch only contributes the posts that are not already on screen', () => {
  const rotation = createFeedRotation()

  assert.deepEqual(rotation.accept(batch(1, 2, 3)).map(post => post.id), [1, 2, 3])
  assert.deepEqual(rotation.accept(batch(3, 4)).map(post => post.id), [4])
})

test('a batch that adds nothing marks the end of the rotation', () => {
  const rotation = createFeedRotation()
  rotation.accept(batch(1, 2))

  assert.deepEqual(rotation.accept(batch(1, 2)), [])
  assert.deepEqual(rotation.accept([]), [])
})

test('the exclusion list carries every id seen so far', () => {
  const rotation = createFeedRotation()
  rotation.accept(batch(7, 8))
  rotation.accept(batch(9))

  assert.deepEqual(rotation.exclude(), [7, 8, 9])
})

test('past the cap the exclusion list keeps the most recently seen ids', () => {
  const rotation = createFeedRotation(3)
  rotation.accept(batch(1, 2, 3, 4, 5))

  assert.deepEqual(rotation.exclude(), [3, 4, 5])
})

test('forgetting the rotation makes the whole catalogue eligible again', () => {
  const rotation = createFeedRotation()
  rotation.accept(batch(1, 2))
  rotation.forget()

  assert.deepEqual(rotation.exclude(), [])
  assert.deepEqual(rotation.accept(batch(1, 2)).map(post => post.id), [1, 2])
})

test('stops loading when the API marks the current page as final', () => {
  assert.equal(feedHasMore({ items: batch(1, 2), has_more: false }), false)
  assert.equal(feedHasMore({ items: batch(...Array.from({ length: 24 }, (_, index) => index)) }), true)
})
