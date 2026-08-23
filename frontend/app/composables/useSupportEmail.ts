const SUPPORT_ADDRESS = 'hello@usepersonal.app'

export function useSupportEmail() {
  const { t } = useI18n()
  const route = useRoute()
  const { user } = useAuth()

  // The draft is prefilled so nobody faces an empty inbox: a greeting, a prompt
  // with an example, and the context we always end up asking for anyway.
  const mailto = computed(() => {
    const subject = t('support.subject')
    const body = t('support.body', {
      account: user.value?.email || t('support.noAccount'),
      page: route.fullPath
    })

    return `mailto:${SUPPORT_ADDRESS}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`
  })

  return { address: SUPPORT_ADDRESS, mailto }
}
