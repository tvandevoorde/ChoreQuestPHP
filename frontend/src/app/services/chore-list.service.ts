import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ChoreList {
  id: number;
  name: string;
  description: string;
  ownerId: number;
  ownerUsername: string;
  createdAt: Date;
  updatedAt: Date;
  choreCount: number;
  completedChoreCount: number;
  shares: Share[];
}

export interface Share {
  id: number;
  sharedWithUserId: number;
  sharedWithUsername: string;
  permission: string;
  sharedAt: Date;
}

export interface CreateChoreListDto {
  name: string;
  description: string;
}

export interface UpdateChoreListDto {
  name?: string;
  description?: string;
}

export interface ShareChoreListDto {
  sharedWithUserId: number;
  permission: string;
}

@Injectable({
  providedIn: 'root'
})
export class ChoreListService {
  private apiUrl = '/api/chorelists';

  constructor(private http: HttpClient) {}

  getChoreLists(userId: number): Observable<ChoreList[]> {
    return this.http.get<ChoreList[]>(`${this.apiUrl}?userId=${userId}`);
  }

  getChoreList(id: number): Observable<ChoreList> {
    return this.http.get<ChoreList>(`${this.apiUrl}/${id}`);
  }

  createChoreList(userId: number, dto: CreateChoreListDto): Observable<ChoreList> {
    return this.http.post<ChoreList>(`${this.apiUrl}?userId=${userId}`, dto);
  }

  updateChoreList(id: number, dto: UpdateChoreListDto): Observable<ChoreList> {
    return this.http.put<ChoreList>(`${this.apiUrl}/${id}`, dto);
  }

  deleteChoreList(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${id}`);
  }

  shareChoreList(id: number, dto: ShareChoreListDto): Observable<Share> {
    return this.http.post<Share>(`${this.apiUrl}/${id}/share`, dto);
  }

  removeShare(choreListId: number, shareId: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/${choreListId}/share/${shareId}`);
  }
}
