import { Component, inject } from '@angular/core';
import { RouterOutlet, RouterLink } from '@angular/router';
import { AsyncPipe, NgIf } from '@angular/common';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatBottomSheetModule } from '@angular/material/bottom-sheet';
import { MatTabsModule } from '@angular/material/tabs';
import { BreakpointObserver, Breakpoints } from '@angular/cdk/layout';

@Component({
  selector: 'app-root',
  imports: [
    RouterOutlet,
    RouterLink,
    AsyncPipe,
    NgIf,
    MatToolbarModule,
    MatIconModule,
    MatButtonModule,
    MatBottomSheetModule,
    MatTabsModule
  ],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App {
  private breakpointObserver = inject(BreakpointObserver);
  isHandset$ = this.breakpointObserver.observe(Breakpoints.Handset);
}
