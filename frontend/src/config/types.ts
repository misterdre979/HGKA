export type FieldType = 'string' | 'text' | 'int' | 'decimal' | 'bool' | 'date' | 'time' | 'enum' | 'fk' | 'json'

export interface LabelSpec {
  cols: string[]
  fmt: string | null
}

export interface ColumnConfig {
  key: string
  label: string
  type: FieldType
  rel?: string
  ref?: string
  money?: boolean
  vals?: string[]
}

export interface FieldConfig {
  name: string
  label: string
  type: FieldType
  required: boolean
  vals?: string[]
  ref?: string
  dep?: string
  help?: string
  min?: number
  max?: number
  money?: boolean
  default?: unknown
}

export interface FilterConfig {
  name: string
  label: string
  type: FieldType
  vals?: string[]
  ref?: string
}

export interface ResourceConfig {
  key: string
  label: string
  module: string
  perms: { view: string[]; create: string[]; update: string[]; delete: string[] }
  readonly: boolean
  customCreate: boolean
  columns: ColumnConfig[]
  fields: FieldConfig[]
  filters: FilterConfig[]
  search: boolean
}
