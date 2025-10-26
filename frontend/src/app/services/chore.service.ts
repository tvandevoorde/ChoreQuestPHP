import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Chore {
  id: number;
  title: string;
  description: string;
  choreListId: number;
  assignedToId?: number;
  assignedToUsername?: string;
  dueDate?: Date;
  isCompleted: boolean;
  completedAt?: Date;
  createdAt: Date;
  updatedAt: Date;
  isRecurring: boolean;
  recurrencePattern?: string;
  recurrenceInterval?: number;
  recurrenceEndDate?: Date;
}

export interface CreateChoreDto {
  title: string;
  description: string;
  assignedToId?: number;
  dueDate?: Date;
  isRecurring: boolean;
  recurrencePattern?: string;
  recurrenceInterval?: number;
  recurrenceEndDate?: Date;
}

export interface UpdateChoreDto {
  title?: string;
  description?: string;
  assignedToId?: number;
  dueDate?: Date;
  isCompleted?: boolean;
  isRecurring?: boolean;
  recurrencePattern?: string;
  recurrenceInterval?: number;
  recurrenceEndDate?: Date;
}

@Injectable({
  providedIn: 'root'
})
export class ChoreService {
  private apiUrl = '/api/chorelists';

  constructor(private http: HttpClient) {}

  getChores(choreListId: number): Observable<Chore[]> {
    return this.http.get<Chore[]>(`${this.apiUrl}/${choreListId}/chores`);
  }

  getChore(choreListId: number, id: number): Observable<Chore> {
    return this.http.get<Chore>(`${this.apiUrl}/${choreListId}/chores/${id}`);
  }

  createChore(choreListId: number, dto: CreateChoreDto): Observable<Chore> {
    return this.http.post<Chore>(`${this.apiUrl}/${choreListId}/chores`, dto);
  }

  updateChore(choreListId: number, id: number, dto: UpdateChoreDto): Observable<Chore> {
    return this.http.put<Chore>(`${this.apiUrl}/${choreListId}/chores/${id}`, dto);
  }

  deleteChore(choreListId: number, id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${choreListId}/chores/${id}`);
  }
}
