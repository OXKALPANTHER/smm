// Operator's Signal Room: order status becomes a concise native queue with no fabricated operational metrics.
import { FlatList, StyleSheet, Text, View } from "react-native";

import { AppIcon } from "@/components/app-icon";
import { Screen } from "@/components/screen";
import { useRoyalApp } from "@/lib/app-state";
import type { OrderRecord } from "@/lib/types";

export default function OrdersScreen() {
  const { bootstrap } = useRoyalApp();
  const orders = bootstrap?.orders ?? [];

  return (
    <Screen>
      <FlatList
        data={orders}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={styles.content}
        ListHeaderComponent={
          <>
            <Text style={styles.eyebrow}>DELIVERY DESK</Text>
            <Text style={styles.title}>Orders</Text>
            <Text style={styles.subtitle}>Track live service delivery once your panel is connected.</Text>
          </>
        }
        ListEmptyComponent={
          <View style={styles.empty}>
            <AppIcon name="clipboard-text-clock-outline" color="#8D93AA" size={30} />
            <Text style={styles.emptyTitle}>No live orders loaded</Text>
            <Text style={styles.emptyText}>The native order queue will populate from the existing panel when the mobile API is available.</Text>
          </View>
        }
        renderItem={({ item }) => <OrderRow item={item} />}
      />
    </Screen>
  );
}

function OrderRow({ item }: { item: OrderRecord }) {
  return (
    <View style={styles.card}>
      <View style={styles.icon}><AppIcon name="package-variant-closed" /></View>
      <View style={styles.copy}>
        <Text style={styles.orderTitle} numberOfLines={1}>{item.serviceName}</Text>
        <Text style={styles.orderMeta}>#{item.id} · {item.platform ?? "Service"} · {item.quantity.toLocaleString()} units</Text>
        <View style={styles.bottom}><Text style={styles.status}>{item.status}</Text><Text style={styles.price}>TSh {item.price.toLocaleString()}</Text></View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  content: { flexGrow: 1, padding: 20, gap: 12 },
  eyebrow: { color: "#6C5CE7", fontSize: 11, fontWeight: "900", letterSpacing: 1.2 },
  title: { marginTop: 3, color: "#252A4D", fontSize: 28, fontWeight: "800" },
  subtitle: { marginTop: 6, marginBottom: 8, color: "#737A95", fontSize: 13, lineHeight: 19 },
  empty: { alignItems: "center", marginTop: 40, padding: 28, borderRadius: 22, borderWidth: 1, borderColor: "#E4E7F0", backgroundColor: "#FFFFFF" },
  emptyTitle: { marginTop: 10, color: "#454C70", fontSize: 15, fontWeight: "800" },
  emptyText: { marginTop: 5, color: "#8087A0", fontSize: 12, lineHeight: 18, textAlign: "center" },
  card: { flexDirection: "row", gap: 12, padding: 14, borderRadius: 19, borderWidth: 1, borderColor: "#E5E8F1", backgroundColor: "#FFFFFF" },
  icon: { width: 42, height: 42, borderRadius: 14, alignItems: "center", justifyContent: "center", backgroundColor: "#EFEDFF" },
  copy: { flex: 1 }, orderTitle: { color: "#30365A", fontSize: 14, fontWeight: "800" },
  orderMeta: { marginTop: 3, color: "#8188A2", fontSize: 11 },
  bottom: { flexDirection: "row", justifyContent: "space-between", alignItems: "center", marginTop: 10 },
  status: { borderRadius: 999, backgroundColor: "#FFF1D5", paddingHorizontal: 8, paddingVertical: 4, color: "#9F6600", fontSize: 10, fontWeight: "800" },
  price: { color: "#3E456C", fontSize: 12, fontWeight: "800" },
});
