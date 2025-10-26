import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../services/auth.service';

@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './reset-password.component.html',
  styleUrl: './reset-password.component.css'
})
export class ResetPasswordComponent implements OnInit {
  token = '';
  newPassword = '';
  confirmPassword = '';
  message = '';
  errorMessage = '';
  isSubmitting = false;

  constructor(
    private authService: AuthService, 
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    // Get token from query parameters
    this.token = this.route.snapshot.queryParams['token'] || '';
    
    if (!this.token) {
      this.errorMessage = 'Invalid reset link. Please request a new password reset.';
    }
  }

  onSubmit(): void {
    this.message = '';
    this.errorMessage = '';

    if (this.newPassword !== this.confirmPassword) {
      this.errorMessage = 'Passwords do not match';
      return;
    }

    if (this.newPassword.length < 6) {
      this.errorMessage = 'Password must be at least 6 characters long';
      return;
    }

    this.isSubmitting = true;

    this.authService.resetPassword(this.token, this.newPassword).subscribe({
      next: (response) => {
        this.message = response.message;
        this.isSubmitting = false;
        // Redirect to login after 2 seconds
        setTimeout(() => {
          this.router.navigate(['/login']);
        }, 2000);
      },
      error: (err) => {
        this.errorMessage = err.error?.message || err.error || 'An error occurred. Please try again.';
        this.isSubmitting = false;
      }
    });
  }
}
