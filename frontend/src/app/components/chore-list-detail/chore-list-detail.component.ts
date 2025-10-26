import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatChipsModule } from '@angular/material/chips';
import { MatExpansionModule } from '@angular/material/expansion';
import { MatBottomSheetModule } from '@angular/material/bottom-sheet';
import { ChoreListService, ChoreList, ShareChoreListDto } from '../../services/chore-list.service';
import { ChoreService, Chore, CreateChoreDto, UpdateChoreDto } from '../../services/chore.service';
import { AuthService, User } from '../../services/auth.service';

@Component({
  selector: 'app-chore-list-detail',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    RouterModule,
    MatToolbarModule,
    MatIconModule,
    MatButtonModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatCheckboxModule,
    MatProgressBarModule,
    MatChipsModule,
    MatExpansionModule,
    MatBottomSheetModule
  ],
  templateUrl: './chore-list-detail.component.html',
  styleUrls: ['./chore-list-detail.component.css']
})
export class ChoreListDetailComponent implements OnInit {
  choreList?: ChoreList;
  chores: Chore[] = [];
  users: User[] = [];
  showCreateChore = false;
  showShareForm = false;
  newChore: CreateChoreDto = {
    title: '',
    description: '',
    isRecurring: false
  };
  shareDto: ShareChoreListDto = {
    sharedWithUserId: 0,
    permission: 'View'
  };
  currentUser: any;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private choreListService: ChoreListService,
    private choreService: ChoreService,
    private authService: AuthService
  ) {}

  ngOnInit(): void {
    this.currentUser = this.authService.getCurrentUser();
    if (!this.currentUser) {
      this.router.navigate(['/login']);
      return;
    }

    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.loadChoreList(id);
    this.loadChores(id);
    this.loadUsers();
  }

  loadChoreList(id: number): void {
    this.choreListService.getChoreList(id).subscribe(
      list => this.choreList = list
    );
  }

  loadChores(choreListId: number): void {
    this.choreService.getChores(choreListId).subscribe(
      chores => this.chores = chores
    );
  }

  loadUsers(): void {
    this.authService.getUsers().subscribe(
      users => this.users = users.filter(u => u.id !== this.currentUser.id)
    );
  }

  createChore(): void {
    if (!this.choreList || !this.newChore.title.trim()) {
      return;
    }

    this.choreService.createChore(this.choreList.id, this.newChore).subscribe({
      next: (chore) => {
        this.chores.push(chore);
        this.newChore = { title: '', description: '', isRecurring: false };
        this.showCreateChore = false;
        this.loadChoreList(this.choreList!.id);
      }
    });
  }

  toggleChoreComplete(chore: Chore): void {
    if (!this.choreList) return;

    const update: UpdateChoreDto = {
      isCompleted: !chore.isCompleted
    };

    this.choreService.updateChore(this.choreList.id, chore.id, update).subscribe({
      next: (updatedChore) => {
        const index = this.chores.findIndex(c => c.id === chore.id);
        if (index !== -1) {
          this.chores[index] = updatedChore;
        }
        this.loadChoreList(this.choreList!.id);
      }
    });
  }

  deleteChore(chore: Chore): void {
    if (!this.choreList || !confirm('Delete this chore?')) return;

    this.choreService.deleteChore(this.choreList.id, chore.id).subscribe({
      next: () => {
        this.chores = this.chores.filter(c => c.id !== chore.id);
        this.loadChoreList(this.choreList!.id);
      }
    });
  }

  shareList(): void {
    if (!this.choreList || !this.shareDto.sharedWithUserId) {
      return;
    }

    this.choreListService.shareChoreList(this.choreList.id, this.shareDto).subscribe({
      next: () => {
        this.loadChoreList(this.choreList!.id);
        this.shareDto = { sharedWithUserId: 0, permission: 'View' };
        this.showShareForm = false;
      }
    });
  }

  removeShare(shareId: number): void {
    if (!this.choreList || !confirm('Remove this share?')) return;

    this.choreListService.removeShare(this.choreList.id, shareId).subscribe({
      next: () => {
        this.loadChoreList(this.choreList!.id);
      }
    });
  }

  isOverdue(chore: Chore): boolean {
    if (!chore.dueDate || chore.isCompleted) return false;
    return new Date(chore.dueDate) < new Date();
  }

  isDueSoon(chore: Chore): boolean {
    if (!chore.dueDate || chore.isCompleted) return false;
    const dueDate = new Date(chore.dueDate);
    const today = new Date();
    const diffDays = Math.ceil((dueDate.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    return diffDays >= 0 && diffDays <= 3;
  }

  getProgressPercentage(): number {
    if (!this.choreList || this.choreList.choreCount === 0) return 0;
    return Math.round((this.choreList.completedChoreCount / this.choreList.choreCount) * 100);
  }

  getPendingCount(): number {
    return this.chores.filter(c => !c.isCompleted).length;
  }
}
