// Operator's Signal Room: role selection changes information priority while retaining one coherent Royal SMM app shell.
import { Pressable, StyleSheet, Text, View } from "react-native";
import { router } from "expo-router";
import * as Haptics from "expo-haptics";

import { AppIcon } from "@/components/app-icon";
import { Screen } from "@/components/screen";
import { useRoyalApp } from "@/lib/app-state";
import type { AppRole } from "@/lib/types";

export default function RoleScreen() {
  const { role, setRole } = useRoyalApp();
  const choose = async (next: AppRole) => { setRole(next); await Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light); router.back(); };
  return <Screen><View style={styles.content}><View><Text style={styles.eyebrow}>ROLE VIEW</Text><Text style={styles.title}>Choose your workspace</Text><Text style={styles.subtitle}>The same app adjusts its operational surfaces to your account responsibility.</Text></View><RoleCard active={role === "customer"} icon="account-outline" title="Customer" copy="Order services, follow delivery, add funds, and receive clear account signals." onPress={() => choose("customer")} /><RoleCard active={role === "admin"} icon="shield-crown-outline" title="Administrator" copy="Review operational context, customer activity, and high-priority notification signals." onPress={() => choose("admin")} /></View></Screen>;
}

function RoleCard({ active, icon, title, copy, onPress }: { active: boolean; icon: Parameters<typeof AppIcon>[0]["name"]; title: string; copy: string; onPress: () => void }) {
  return <Pressable onPress={onPress} style={({ pressed }) => [styles.card, active && styles.cardActive, pressed && styles.pressed]}><View style={[styles.icon, active && styles.iconActive]}><AppIcon name={icon} color={active ? "#FFFFFF" : "#6C5CE7"} size={27} /></View><View style={{ flex: 1 }}><Text style={[styles.cardTitle, active && styles.cardTitleActive]}>{title}</Text><Text style={[styles.cardCopy, active && styles.cardCopyActive]}>{copy}</Text></View><AppIcon name={active ? "check-circle" : "chevron-right"} color={active ? "#FFFFFF" : "#8B91AA"} size={21} /></Pressable>;
}

const styles = StyleSheet.create({ content: { flex: 1, justifyContent: "center", padding: 22, gap: 14 }, eyebrow: { color: "#6C5CE7", fontSize: 11, letterSpacing: 1.2, fontWeight: "900" }, title: { marginTop: 5, color: "#252A4D", fontSize: 27, lineHeight: 33, fontWeight: "800" }, subtitle: { marginTop: 7, marginBottom: 9, color: "#737A95", fontSize: 13, lineHeight: 19 }, card: { flexDirection: "row", alignItems: "center", gap: 13, padding: 16, borderRadius: 22, borderWidth: 1, borderColor: "#E2E5F0", backgroundColor: "#FFFFFF" }, cardActive: { borderColor: "#6C5CE7", backgroundColor: "#6C5CE7" }, icon: { width: 49, height: 49, borderRadius: 17, alignItems: "center", justifyContent: "center", backgroundColor: "#EFEDFF" }, iconActive: { backgroundColor: "rgba(255,255,255,.16)" }, cardTitle: { color: "#343B61", fontSize: 15, fontWeight: "800" }, cardTitleActive: { color: "#FFFFFF" }, cardCopy: { marginTop: 4, color: "#7C839E", fontSize: 11, lineHeight: 16 }, cardCopyActive: { color: "#E5E3FC" }, pressed: { opacity: 0.8, transform: [{ scale: 0.97 }] } });
