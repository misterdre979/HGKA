import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { z } from 'zod'
import type { FieldConfig } from '@/config/types'
import { FkSelect } from '@/components/FkSelect'
import { Button, Input, Label, Select, Textarea } from '@/components/ui'
import { useToast } from '@/context/ToastContext'
import { errMsg, fieldErrors, humanize } from '@/lib/utils'

type Values = Record<string, any>

/** Builds a Zod schema from generated field config. Empty optional values become null. */
export function buildSchema(fields: FieldConfig[]) {
  const shape: Record<string, z.ZodType> = {}
  for (const f of fields) {
    let s: z.ZodType
    const req = f.required
    const requiredMsg = `${f.label} is required`
    switch (f.type) {
      case 'int':
      case 'decimal':
      case 'fk': {
        let num = z.number({ error: `${f.label} must be a number` })
        if (f.type !== 'decimal') num = num.int(`${f.label} must be a whole number`)
        if (f.min !== undefined) num = num.min(f.min, `Minimum is ${f.min}`)
        if (f.max !== undefined) num = num.max(f.max, `Maximum is ${f.max}`)
        const blank = (v: unknown) => v === '' || v === null || v === undefined
        s = req
          ? z.preprocess((v) => (blank(v) ? undefined : Number(v)), num)
          : z.preprocess((v) => (blank(v) ? null : Number(v)), num.nullable())
        break
      }
      case 'bool':
        s = z.boolean()
        break
      case 'enum': {
        const e = z.string()
        s = req ? e.min(1, requiredMsg) : z.preprocess((v) => (v === '' ? null : v), e.nullable())
        break
      }
      case 'json':
        s = z.any()
        break
      default: {
        const str = z.string()
        s = req ? str.trim().min(1, requiredMsg) : z.preprocess((v) => (v === '' || v === undefined ? null : v), str.nullable())
      }
    }
    shape[f.name] = s
  }
  return z.object(shape)
}

function defaults(fields: FieldConfig[], initial?: Values): Values {
  const out: Values = {}
  for (const f of fields) {
    const v = initial?.[f.name]
    if (f.type === 'bool') out[f.name] = v ?? (f.default as boolean | undefined) ?? false
    else if (f.type === 'date' && typeof v === 'string') out[f.name] = v.slice(0, 10)
    else if (f.type === 'time' && typeof v === 'string') out[f.name] = v.slice(0, 5)
    else if (f.type === 'json') out[f.name] = v ?? null
    else out[f.name] = v ?? (f.default !== undefined ? f.default : '')
  }
  return out
}

export function ResourceForm({ fields, initial, submitLabel = 'Save', onSubmit, onCancel }: {
  fields: FieldConfig[]
  initial?: Values
  submitLabel?: string
  onSubmit: (values: Values) => Promise<void>
  onCancel?: () => void
}) {
  const toast = useToast()
  const schema = useMemo(() => buildSchema(fields), [fields])
  const { register, control, handleSubmit, setError, watch, setValue, formState: { errors, isSubmitting } } = useForm<Values>({
    resolver: zodResolver(schema as any) as any,
    defaultValues: defaults(fields, initial),
  })

  const submit = handleSubmit(async (values) => {
    try {
      await onSubmit(values)
    } catch (e) {
      const fe = fieldErrors(e)
      const names = new Set(fields.map((f) => f.name))
      let matched = false
      for (const [k, msg] of Object.entries(fe)) {
        if (names.has(k)) { setError(k, { message: msg }); matched = true }
      }
      if (!matched) toast.err(errMsg(e))
    }
  })

  return (
    <form onSubmit={submit} className="space-y-4" noValidate>
      <div className="grid gap-4 sm:grid-cols-2">
        {fields.map((f) => {
          const err = errors[f.name]?.message as string | undefined
          const full = f.type === 'text' || f.type === 'json'
          const dep = f.dep ? watch(f.dep) : undefined
          return (
            <label key={f.name} className={full ? 'sm:col-span-2' : ''}>
              {f.type !== 'bool' && <Label required={f.required} help={f.help}>{f.label}</Label>}
              {f.type === 'text' && <Textarea {...register(f.name)} />}
              {(f.type === 'string') && <Input {...register(f.name)} />}
              {(f.type === 'int') && <Input type="number" step="1" min={f.min} max={f.max} {...register(f.name)} />}
              {(f.type === 'decimal') && <Input type="number" step="0.01" min={f.min} max={f.max} inputMode="decimal" {...register(f.name)} />}
              {f.type === 'date' && <Input type="date" {...register(f.name)} />}
              {f.type === 'time' && <Input type="time" {...register(f.name)} />}
              {f.type === 'enum' && (
                <Select {...register(f.name)}>
                  <option value="">Select…</option>
                  {f.vals?.map((v) => <option key={v} value={v}>{humanize(v)}</option>)}
                </Select>
              )}
              {f.type === 'bool' && (
                <span className="flex items-center gap-2 pt-6 text-sm font-medium text-slate-700">
                  <input type="checkbox" className="h-4 w-4 rounded border-slate-300 text-royal" {...register(f.name)} /> {f.label}
                  {f.help && <span className="text-xs font-normal text-slate-400">{f.help}</span>}
                </span>
              )}
              {f.type === 'json' && (
                <Controller control={control} name={f.name} render={({ field }) => (
                  <Textarea value={typeof field.value === 'string' ? field.value : field.value ? JSON.stringify(field.value) : ''} onChange={(e) => field.onChange(e.target.value)} placeholder="Comma-separated values" />
                )} />
              )}
              {f.type === 'fk' && f.ref && (
                <Controller control={control} name={f.name} render={({ field }) => (
                  <FkSelect
                    resource={f.ref!} value={field.value} onChange={(v) => { field.onChange(v); if (f.name === 'school_class_id') setValue('class_arm_id', '') }}
                    filter={f.dep ? { [f.dep]: dep as string } : undefined} needsFilter={!!f.dep}
                  />
                )} />
              )}
              {err && <span role="alert" className="mt-1 block text-xs text-red-600">{err}</span>}
            </label>
          )
        })}
      </div>
      <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
        {onCancel && <Button variant="secondary" onClick={onCancel}>Cancel</Button>}
        <Button type="submit" loading={isSubmitting}>{submitLabel}</Button>
      </div>
    </form>
  )
}
