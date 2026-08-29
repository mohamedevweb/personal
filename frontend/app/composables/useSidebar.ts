/**
 * The desktop rail can be folded down to its icons. The choice is kept in a
 * cookie rather than in localStorage so the server renders the rail at the
 * width the user left it at — read after mount, the rail would flash open on
 * every page load.
 */
export function useSidebar() {
  const collapsed = useCookie<boolean>('personal_sidebar_collapsed', {
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 365,
    default: () => false
  })

  function toggle() {
    collapsed.value = !collapsed.value
  }

  return { collapsed, toggle }
}
