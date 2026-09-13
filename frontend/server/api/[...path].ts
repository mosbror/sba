export default defineEventHandler(async (event) => {
  const config = useRuntimeConfig()
  const path = event.context.params?.path || ''
  const method = getMethod(event)
  const query = getQuery(event)
  const body = method === 'GET' || method === 'HEAD' ? undefined : await readBody(event)

  try {
    return await $fetch(`${config.apiInternalBase}/${path}`, {
      method,
      query,
      body,
      headers: {
        accept: 'application/json'
      }
    })
  } catch (error: any) {
    throw createError({
      statusCode: error?.statusCode || 500,
      statusMessage: error?.statusMessage || 'API request failed',
      data: error?.data
    })
  }
})
