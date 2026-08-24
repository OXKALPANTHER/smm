// Operator's Signal Room: semantic icons reinforce notification priority without decorative overhead.
import MaterialCommunityIcons from "@expo/vector-icons/MaterialCommunityIcons";
import type { ComponentProps } from "react";

type IconName = ComponentProps<typeof MaterialCommunityIcons>["name"];

export function AppIcon({ name, color = "#6C5CE7", size = 22 }: { name: IconName; color?: string; size?: number }) {
  return <MaterialCommunityIcons name={name} color={color} size={size} />;
}
