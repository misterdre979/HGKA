export type RoleSlug =
  | 'super-admin'
  | 'school-admin'
  | 'principal'
  | 'vice-principal'
  | 'head-of-department'
  | 'teacher'
  | 'accountant'
  | 'bursar'
  | 'librarian'
  | 'school-nurse'
  | 'transport-manager'
  | 'student'
  | 'parent'
  | 'hr-officer'

export interface User {
  id: number
  name: string
  email: string
  phone?: string | null
  avatar_url?: string | null
  must_change_password: boolean
  roles: RoleSlug[]
  role_names: string[]
  permissions: string[]
  staff_id: number | null
  student_id: number | null
  guardian_id: number | null
}

export interface LoginResponse {
  token: string
  user: User
}
