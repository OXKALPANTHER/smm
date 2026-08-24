// Operator's Signal Room: notification rows expose urgency, scope, and timing in one small native surface.
import { Pressable, StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/app-icon";
import type { NotificationRecord } from "@/lib/types";

const appearance = {
  info: { color: "#5D67B5", surface: "#EAECF9", icon: "information-outline" as const },
  success: { color: "#148457", surface: "#E4F8EE", icon: "check-circle-outline" as const },
  warning: { color: "#A56800", surface: "#FFF3D7", icon: "alert-outline" as const },
  danger: { color: "#C4483C", surface: "#FFE9E6", icon: "shield-alert-outline" as const },
};

export function NotificationRow({ item, onPress }: { item: NotificationRecord; onPress?: () => void }) {
  const tone = appearance[item.type];
  return <Pressable onPress={onPress} style={({ pressed }) => [styles.row, pressed && styles.pressed]}><View style={[styles.icon, { backgroundColor: tone.surface }]}><AppIcon name={tone.icon} color={tone.color} size={20} /></View><View style={styles.copy}><View style={styles.titleRow}><Text style={styles.title} numberOfLines={1}>{item.title}</Text><Text style={styles.time}>{item.createdAt}</Text></View><Text style={styles.message} numberOfLines={2}>{item.message}</Text><View style={styles.meta}><Text style={[styles.scope, item.target === "broadcast" && styles.broadcast]}>{item.target === "broadcast" ? "Broadcast" : item.target === "admin" ? "Admin" : "Customer"}</Text>{item.status === "unread" && <View style={[styles.dot, { backgroundColor: tone.color }]} />}</View></View></Pressable>;
}

const styles = StyleSheet.create({
  row: { flexDirection: "row", gap: 12, paddingVertical: 14, borderBottomWidth: 1, borderBottomColor: "#EBEDF6" }, pressed: { opacity: 0.72 }, icon: { width: 40, height: 40, borderRadius: 14, alignItems: "center", justifyContent: "center" }, copy: { flex: 1, minWidth: 0 }, titleRow: { flexDirection: "row", gap: 8, alignItems: "center" }, title: { flex: 1, color: "#252A4D", fontSize: 15, lineHeight: 20, fontWeight: "800" }, time: { color: "#8D93AA", fontSize: 11, fontWeight: "700" }, message: { marginTop: 3, color: "#6E7590", fontSize: 13, lineHeight: 18 }, meta: { flexDirection: "row", gap: 7, alignItems: "center", marginTop: 7 }, scope: { overflow: "hidden", borderRadius: 999, backgroundColor: "#F0F2F8", paddingHorizontal: 7, paddingVertical: 3, color: "#68708D", fontSize: 10, fontWeight: "800" }, broadcast: { backgroundColor: "#EEEAFF", color: "#6856D6" }, dot: { width: 7, height: 7, borderRadius: 4 },
});
