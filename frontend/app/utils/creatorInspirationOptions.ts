import type { CreatorInspiration } from '~/types/instagram'

interface CreatorOptionGroups {
  results: CreatorInspiration[]
  suggestions: CreatorInspiration[]
}

function uniqueAvailableCreators(creators: CreatorInspiration[], unavailable: Set<string>) {
  return Array.from(new Map(creators.map(creator => [creator.username.toLowerCase(), creator])).values())
    .filter(creator => !unavailable.has(creator.username.toLowerCase()))
}

export function groupCreatorOptions(
  searchResults: CreatorInspiration[],
  suggestions: CreatorInspiration[],
  selectedUsernames: string[],
  suggestionLimit: number
): CreatorOptionGroups {
  const selected = new Set(selectedUsernames.map(username => username.toLowerCase()))
  const results = uniqueAvailableCreators(searchResults, selected)
  const searchedUsernames = new Set(searchResults.map(creator => creator.username.toLowerCase()))

  return {
    results,
    suggestions: uniqueAvailableCreators(suggestions, new Set([...selected, ...searchedUsernames]))
      .slice(0, suggestionLimit)
  }
}
