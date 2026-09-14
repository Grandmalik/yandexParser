import { http, post } from '@/shared/api/http';
import type { LoginData, UserData } from '@/types/generated/api';

export function login(credentials: LoginData): Promise<UserData> {
    return post<UserData>('/auth/login', credentials);
}

export async function logout(): Promise<void> {
    await http.post('/auth/logout');
}

export async function currentUser(): Promise<UserData> {
    return (await http.get<{ data: UserData }>('/auth/me')).data.data;
}
