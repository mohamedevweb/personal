import assert from 'node:assert/strict'
import test from 'node:test'

import { groupCreatorOptions } from '../app/utils/creatorInspirationOptions.ts'

function creator(username) {
  return {
    username,
    display_name: username,
    avatar_url: null,
    followers: 1000,
    niche: null,
    is_selected: false,
    is_measured: true
  }
}

test('keeps curated suggestions available when a search returns no result', () => {
  const suggestions = Array.from({ length: 8 }, (_, index) => creator(`suggestion_${index + 1}`))

  const groups = groupCreatorOptions([], suggestions, [], 6)

  assert.deepEqual(groups.results, [])
  assert.deepEqual(groups.suggestions.map(option => option.username), [
    'suggestion_1',
    'suggestion_2',
    'suggestion_3',
    'suggestion_4',
    'suggestion_5',
    'suggestion_6'
  ])
})

test('shows a search hit separately and refills the suggestion list from its reserve', () => {
  const suggestions = Array.from({ length: 8 }, (_, index) => creator(`suggestion_${index + 1}`))

  const groups = groupCreatorOptions([creator('suggestion_1')], suggestions, [], 6)

  assert.deepEqual(groups.results.map(option => option.username), ['suggestion_1'])
  assert.deepEqual(groups.suggestions.map(option => option.username), [
    'suggestion_2',
    'suggestion_3',
    'suggestion_4',
    'suggestion_5',
    'suggestion_6',
    'suggestion_7'
  ])
})
