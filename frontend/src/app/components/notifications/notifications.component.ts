import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule, Router } from '@angular/router';
import { NotificationService, Notification } from '../../services/notification.service';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-notifications',
  standalone: true,
  imports: [CommonModule, RouterModule],
  templateUrl: './notifications.component.html',
  styleUrls: ['./notifications.component.css']
})
export class NotificationsComponent implements OnInit {
  notifications: Notification[] = [];
  currentUser: any;

  constructor(
    private notificationService: NotificationService,
    private authService: AuthService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.currentUser = this.authService.getCurrentUser();
    if (!this.currentUser) {
      this.router.navigate(['/login']);
      return;
    }
    this.loadNotifications();
  }

  loadNotifications(): void {
    this.notificationService.getNotifications(this.currentUser.id).subscribe(
      notifications => this.notifications = notifications
    );
  }

  markAsRead(notification: Notification): void {
    if (!notification.isRead) {
      this.notificationService.markAsRead(notification.id).subscribe({
        next: () => {
          notification.isRead = true;
        }
      });
    }
  }

  markAllAsRead(): void {
    this.notificationService.markAllAsRead(this.currentUser.id).subscribe({
      next: () => {
        this.notifications.forEach(n => n.isRead = true);
      }
    });
  }

  deleteNotification(id: number): void {
    this.notificationService.deleteNotification(id).subscribe({
      next: () => {
        this.notifications = this.notifications.filter(n => n.id !== id);
      }
    });
  }

  getNotificationIcon(type: string): string {
    switch (type) {
      case 'ChoreAssigned': return '📌';
      case 'ChoreDueSoon': return '⏰';
      case 'ChoreCompleted': return '✅';
      case 'ListShared': return '🤝';
      case 'ChoreOverdue': return '⚠️';
      default: return '📬';
    }
  }
}
