export type AppRole = "customer" | "admin";
export type NotificationKind = "info" | "success" | "warning" | "danger";

export type NotificationRecord = {
  id: number;
  title: string;
  message: string;
  type: NotificationKind;
  target: "user" | "broadcast" | "admin";
  status: "read" | "unread";
  createdAt: string;
};

export type OrderRecord = {
  id: number;
  serviceName: string;
  platform: string | null;
  quantity: number;
  price: number;
  status: string;
  createdAt: string;
};

export type MobileBootstrap = {
  user: { id: number; username: string; role: AppRole; balance: number };
  notifications: NotificationRecord[];
  orders: OrderRecord[];
  unreadNotificationCount: number;
};

export type NativeApiError = { message: string; status?: number };
