import { apiGet } from './api'
import type { BootstrapData } from '@/types'

export const fetchBootstrap = async() => apiGet<BootstrapData>('/api/v1/bootstrap')