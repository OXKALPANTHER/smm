// Operator's Signal Room: the native inbox is a filterable signal rail, not a generic list of messages.
import { FlatList, Pressable, StyleSheet, Text, View } from "react-native";
import { useMemo, useState } from "react";

import { AppIcon } from "@/components/app-icon";
import { NotificationRow } from "@/components/notification-row";
import { Screen } from "@/components/screen";
import { useRoyalApp } from "@/lib/app-state";
import type { NotificationKind } from "@/lib/types";

const filters: { label: string; value: "all" | NotificationKind }[] = [
  { label: "All", value: "all" }, { label: "Alerts", value: "warning" }, { label: "Urgent", value: "danger" }, { label: "Done", value: "success" },
];

export default function NotificationsScreen() {
  const { bootstrap, role } = useRoyalApp();
  const [filter, setFilter] = useState<"all" | NotificationKind>("all");
  const notifications = useMemo(() => (bootstrap?.notifications ?? []).filter((item) => filter === "all" || item.type === filter), [bootstrap, filter]);

  return (
    <Screen>
      <FlatList
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.content}
        ListHeaderComponent={
          <>
            <Text style={styles.eyebrow}>{role === "admin" ? "ADMIN SIGNAL RAIL" : "SIGNAL INBOX"}</Text>
            <Text style={styles.title}>Notifications</Text>
            <Text style={styles.subtitle}>{role === "admin" ? "The native admin composer connects after the role-validated notification API is enabled." : "Scope, urgency, and the next piece of context in one native inbox."}</Text>
            <View style={styles.filters}>{filters.map((item) => <Pressable key={item.value} onPress={() => setFilter(item.value)} style={({ pressed }) => [styles.filter, filter === item.value && styles.filterActive, pressed && styles.pressed]}><Text style={[styles.filterText, filter === item.value && styles.filterTextActive]}>{item.label}</Text></Pressable>)}</View>
          </>
        }
        ListEmptyComponent={
          <View style={styles.empty}>
            <AppIcon name="bell-sleep-outline" color="#8D93AA" size={31} />
            <Text style={styles.emptyTitle}>Your signal rail is quiet</Text>
            <Text style={styles.emptyText}>Live notifications will appear here after the panel is connected.</Text>
          </View>
        }
        renderItem={({ item }) => <NotificationRow item={item} />}
      />
    </Screen>
  );
}

const styles = StyleSheet.create({
  content: { flexGrow: 1, padding: 20, paddingBottom: 32 },
  eyebrow: { color: "#6C5CE7", fontSize: 11, letterSpacing: 1.2, fontWeight: "900" },
  title: { marginTop: 3, color: "#252A4D", fontSize: 28, fontWeight: "800" },
  subtitle: { marginTop: 6, color: "#737A95", fontSize: 13, lineHeight: 19 },
  filters: { flexDirection: "row", gap: 7, marginTop: 18, marginBottom: 7 },
  filter: { borderRadius: 10, paddingVertical: 8, paddingHorizontal: 11, backgroundColor: "#ECEEF7" },
  filterActive: { backgroundColor: "#E9E5FF" }, filterText: { color: "#77809C", fontSize: 11, fontWeight: "800" }, filterTextActive: { color: "#604FD2" },
  pressed: { opacity: 0.75, transform: [{ scale: 0.97 }] },
  empty: { alignItems: "center", marginTop: 34, padding: 30, borderRadius: 22, borderWidth: 1, borderColor: "#E4E7F0", backgroundColor: "#FFFFFF" },
  emptyTitle: { marginTop: 10, color: "#454C70", fontSize: 15, fontWeight: "800" }, emptyText: { marginTop: 5, color: "#8087A0", fontSize: 12, lineHeight: 18, textAlign: "center" },
});

