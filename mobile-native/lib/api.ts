import type { MobileBootstrap, NativeApiError, NotificationKind, NotificationRecord } from "@/lib/types";

/**
 * Operator's Signal Room API contract. The current PHP product is page and
 * session based; these typed calls define the small JSON layer a live native app requires.
 */
export class RoyalApiClient {
  constructor(private readonly baseUrl: string, private readonly token?: string) {}

  private async request<T>(path: string, options: RequestInit = {}): Promise<T> {
    const base = this.baseUrl.replace(/\/$/, "");
    const response = await fetch(`${base}${path}`, {
      ...options,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(this.token ? { Authorization: `Bearer ${this.token}` } : {}),
        ...(options.headers ?? {}),
      },
    });
    if (!response.ok) {
      const error: NativeApiError = { message: "The live panel could not be reached.", status: response.status };
      throw error;
    }
    return response.json() as Promise<T>;
  }

  getBootstrap() { return this.request<MobileBootstrap>("/api/mobile/bootstrap"); }
  getNotifications() { return this.request<NotificationRecord[]>("/api/mobile/notifications"); }
  markNotificationRead(notificationId: number) { return this.request<void>(`/api/mobile/notifications/${notificationId}/read`, { method: "POST" }); }
  sendAdminNotification(input: { target: "user" | "broadcast" | "admin"; userId?: number; title: string; message: string; type: NotificationKind }) {
    return this.request<NotificationRecord>("/api/mobile/admin/notifications", { method: "POST", body: JSON.stringify(input) });
  }
}
