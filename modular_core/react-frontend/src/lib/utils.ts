import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

/**
 * Enterprise Utility for dynamic Tailwind class combining.
 * Requirement: shadcn/ui foundation.
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
